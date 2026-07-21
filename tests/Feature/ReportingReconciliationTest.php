<?php

use App\Enums\RunStatus;
use App\Http\Resources\Maacc\AgentRunResource;
use App\Models\LlmProvider;
use App\Support\Observability\RunMetrics;
use App\Support\Runtime\ModelPricing;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Schema;

test('model quotes carry governed source-currency provenance', function () {
    config()->set('maacc.pricing', [
        'currency' => 'USD',
        'unit' => 'per_million_tokens',
        'source' => 'Finance-approved catalog',
        'version' => '2026.07',
        'effective_at' => '2026-07-01T00:00:00Z',
        'models' => ['approved/model' => ['input' => 2.5, 'output' => 10]],
    ]);
    $provider = LlmProvider::factory()->make(['code' => 'approved/model']);

    $quote = app(ModelPricing::class)->quoteFor($provider);

    expect($quote)->toMatchArray([
        'input' => 2.5,
        'output' => 10.0,
        'currency' => 'USD',
        'unit' => 'per_million_tokens',
        'source' => 'Finance-approved catalog',
        'version' => '2026.07',
        'effectiveAt' => '2026-07-01T00:00:00Z',
    ]);
});

test('dashboard usage and cost reconcile to authoritative run facts', function () {
    Date::setTestNow('2026-07-19 10:30:00');
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    maaccRun($agent, [
        'status' => RunStatus::Completed,
        'tokens_in' => 100,
        'tokens_out' => 50,
        'cost' => 1.25,
        'cost_currency' => 'USD',
        'caller_subject' => 'employee-123',
        'caller_department' => 'Operations',
        'started_at' => now()->subHour(),
        'created_at' => now()->subHour(),
    ]);
    maaccRun($agent, [
        'status' => RunStatus::Failed,
        'tokens_in' => 75,
        'tokens_out' => 0,
        'cost' => 0.75,
        'cost_currency' => 'USD',
        'caller_subject' => 'employee-123',
        'caller_department' => 'Operations',
        'started_at' => now()->subMinutes(10),
        'created_at' => now()->subMinutes(10),
    ]);

    $report = app(RunMetrics::class)->forTeam($team);

    expect($report['stats'])->toMatchArray([
        'runsToday' => 2,
        'success' => 1,
        'failed' => 1,
        'tokens' => '225',
        'cost' => 'USD 2',
        'costEstimated' => true,
    ])->and(array_sum($report['runsOverTime']))->toBe(2)
        ->and($report['usageByUser'][0])->toMatchArray(['runs' => 2, 'tokens' => 225, 'cost' => 2.0])
        ->and($report['usageByUser'][0]['key'])->not->toContain('employee-123')
        ->and($report['usageByDepartment'][0]['key'])->not->toContain('Operations')
        ->and($report['reporting']['source'])->toBe('agent_runs')
        ->and($report['reporting']['measuredAt'])->toContain('2026-07-19T10:30:00');
});

test('run resources expose currency pricing provenance and atomic timestamps', function () {
    [, $team] = ownerAndTeam();
    $run = maaccRun(maaccAgent($team), [
        'cost' => 1.5,
        'cost_currency' => 'USD',
        'pricing_source' => 'Finance-approved catalog',
        'pricing_version' => '2026.07',
        'pricing_effective_at' => '2026-07-01 00:00:00',
        'started_at' => '2026-07-19 08:00:00',
        'completed_at' => '2026-07-19 08:00:02',
    ])->load(['agent', 'application', 'project', 'llmProvider']);

    $payload = (new AgentRunResource($run))->resolve();

    expect($payload)->toMatchArray([
        'cost' => 1.5,
        'currency' => 'USD',
        'pricing' => [
            'source' => 'Finance-approved catalog',
            'version' => '2026.07',
            'effectiveAt' => '2026-07-01T00:00:00+00:00',
            'estimated' => true,
        ],
    ])->and($payload['startedAt'])->toContain('2026-07-19T08:00:00')
        ->and($payload['completedAt'])->toContain('2026-07-19T08:00:02');
});

test('console filter and sort columns have compound indexes', function () {
    $agentRunIndexes = collect(Schema::getIndexes('agent_runs'))->pluck('name');
    $approvalIndexes = collect(Schema::getIndexes('approval_requests'))->pluck('name');

    expect($agentRunIndexes)->toContain(
        'agent_runs_app_status_created_index',
        'agent_runs_project_status_created_index',
        'agent_runs_agent_created_index',
    )->and($approvalIndexes)->toContain(
        'approval_team_status_created_index',
        'approval_project_status_created_index',
    );
});

test('caller reporting rejects unsupported dimensions', function () {
    [, $team] = ownerAndTeam();
    $method = new ReflectionMethod(app(RunMetrics::class), 'usageBreakdown');

    expect(fn () => $method->invoke(app(RunMetrics::class), $team, 'raw_identity', now()))
        ->toThrow(InvalidArgumentException::class, 'Unsupported caller reporting dimension');
});
