<?php

use App\Enums\Environment;
use App\Enums\QuotaScope;
use App\Exceptions\Sdk\RuntimeRequestException;
use App\Models\AgentRun;
use App\Models\GovernanceSetting;
use App\Models\QuotaLimit;
use App\Support\Governance\QuotaGuard;

beforeEach(function () {
    $this->travelTo(now()->startOfDay()->addHours(12));
    [, $this->team] = ownerAndTeam();
    $this->agent = maacAgent($this->team);
    $this->application = $this->agent->project->application;
    $this->guard = app(QuotaGuard::class);
});

/**
 * Create N runs today for the test agent in the given environment.
 */
function quotaFillRuns(int $count, string $environment = 'production'): void
{
    for ($i = 0; $i < $count; $i++) {
        maacRun(test()->agent, [
            'started_at' => now(),
            'environment' => $environment,
            'tokens_in' => 100,
            'tokens_out' => 50,
        ]);
    }
}

/**
 * Assert the guard rejects the run with a quota_exceeded error.
 */
function assertQuotaBlocked(Closure $callback): void
{
    try {
        $callback();
        test()->fail('Expected a quota_exceeded exception.');
    } catch (RuntimeRequestException $exception) {
        expect($exception->errorCode)->toBe('quota_exceeded')
            ->and($exception->status)->toBe(429);
    }
}

test('a run under all quotas is allowed', function () {
    QuotaLimit::factory()->for($this->team)->create(['scope' => QuotaScope::Platform, 'max_runs_per_day' => 5]);
    quotaFillRuns(2);

    $this->guard->assert($this->application, $this->agent, Environment::Production);

    expect(true)->toBeTrue();
});

test('the platform run quota is enforced', function () {
    QuotaLimit::factory()->for($this->team)->create(['scope' => QuotaScope::Platform, 'max_runs_per_day' => 2]);
    quotaFillRuns(2);

    assertQuotaBlocked(fn () => $this->guard->assert($this->application, $this->agent, Environment::Production));
});

test('the token quota is enforced', function () {
    QuotaLimit::factory()->for($this->team)->create([
        'scope' => QuotaScope::Platform,
        'max_runs_per_day' => null,
        'max_tokens_per_day' => 100,
    ]);
    quotaFillRuns(1);

    assertQuotaBlocked(fn () => $this->guard->assert($this->application, $this->agent, Environment::Production));
});

test('application, project, and model scoped quotas match the run dimension', function () {
    foreach ([
        [QuotaScope::Application, $this->application->id],
        [QuotaScope::Project, $this->agent->project_id],
        [QuotaScope::Model, $this->agent->llm_provider_id],
    ] as [$scope, $subjectId]) {
        QuotaLimit::query()->delete();
        AgentRun::query()->delete();
        QuotaLimit::factory()->for($this->team)->create(['scope' => $scope, 'subject_id' => $subjectId, 'max_runs_per_day' => 1]);
        quotaFillRuns(1);

        assertQuotaBlocked(fn () => $this->guard->assert($this->application, $this->agent, Environment::Production));
    }
});

test('the default governance run quota is enforced', function () {
    GovernanceSetting::factory()->for($this->team)->create(['default_daily_run_quota' => 1]);
    quotaFillRuns(1);

    assertQuotaBlocked(fn () => $this->guard->assert($this->application, $this->agent, Environment::Production));
});

test('an environment-specific quota applies only to its environment', function () {
    QuotaLimit::factory()->for($this->team)->create([
        'scope' => QuotaScope::Platform,
        'environment' => Environment::Staging,
        'max_runs_per_day' => 1,
    ]);
    quotaFillRuns(1, 'production');

    // Production runs are unaffected by the staging quota.
    $this->guard->assert($this->application, $this->agent, Environment::Production);

    quotaFillRuns(1, 'staging');
    assertQuotaBlocked(fn () => $this->guard->assert($this->application, $this->agent, Environment::Staging));
});

test('a disabled quota is not enforced', function () {
    QuotaLimit::factory()->for($this->team)->create(['scope' => QuotaScope::Platform, 'max_runs_per_day' => 1, 'enabled' => false]);
    quotaFillRuns(3);

    $this->guard->assert($this->application, $this->agent, Environment::Production);

    expect(true)->toBeTrue();
});

test('a quota scoped to a different subject does not apply', function () {
    $otherAgent = maacAgent($this->team);
    QuotaLimit::factory()->for($this->team)->create(['scope' => QuotaScope::Agent, 'subject_id' => $otherAgent->id, 'max_runs_per_day' => 1]);
    quotaFillRuns(3);

    $this->guard->assert($this->application, $this->agent, Environment::Production);

    expect(true)->toBeTrue();
});
