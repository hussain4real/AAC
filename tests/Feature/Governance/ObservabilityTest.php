<?php

use App\Enums\CredentialStatus;
use App\Enums\RunStatus;
use App\Enums\ToolCallStatus;
use App\Models\Application;
use App\Models\ApprovalRequest;
use App\Models\Credential;
use App\Models\ToolCall;
use App\Support\Observability\OperationalMonitor;
use App\Support\Observability\RunMetrics;

beforeEach(function () {
    // Anchor "now" at midday so today-scoped windows are stable.
    $this->travelTo(now()->startOfDay()->addHours(12));
});

test('run metrics aggregate today\'s runs, status, trend, and top agents', function () {
    [, $team] = ownerAndTeam();
    $agent = maacAgent($team, ['name' => 'Ops Agent']);

    maacRun($agent, ['status' => RunStatus::Completed, 'tokens_in' => 1000, 'tokens_out' => 500, 'cost' => 0.02, 'started_at' => now()->subHours(1)]);
    maacRun($agent, ['status' => RunStatus::Failed, 'started_at' => now()->subHours(2)]);
    maacRun($agent, ['status' => RunStatus::WaitingForClient, 'started_at' => now()->subMinutes(30)]);

    $metrics = app(RunMetrics::class)->forTeam($team);

    expect($metrics['stats']['runsToday'])->toBe(3)
        ->and($metrics['stats']['success'])->toBe(1)
        ->and($metrics['stats']['failed'])->toBe(1)
        ->and($metrics['stats']['waitingClient'])->toBe(1)
        ->and($metrics['stats']['agents'])->toBe(1)
        ->and($metrics['stats']['apps'])->toBe(1)
        ->and($metrics['stats']['cost'])->toContain('QAR')
        ->and($metrics['runStatus'])->toHaveCount(6)
        ->and(collect($metrics['runStatus'])->firstWhere('label', 'Completed')['value'])->toBe(1)
        ->and($metrics['runsOverTime'])->toHaveCount(24)
        ->and(array_sum($metrics['runsOverTime']))->toBe(3)
        ->and($metrics['topAgents'][0]['id'])->toBe($agent->slug)
        ->and($metrics['topAgents'][0]['runs'])->toBe(3);
});

test('operational monitor reports metrics and a severity-sorted alert feed', function () {
    [, $team] = ownerAndTeam();
    $agent = maacAgent($team);

    maacRun($agent, ['status' => RunStatus::Completed, 'latency_ms' => 2000, 'started_at' => now()->subHours(1)]);
    maacRun($agent, ['status' => RunStatus::Failed, 'error' => 'schema mismatch', 'started_at' => now()->subHours(2)]);
    maacRun($agent, ['status' => RunStatus::Expired, 'started_at' => now()->subHours(3)]);
    maacRun($agent, ['status' => RunStatus::WaitingForClient, 'started_at' => now()->subMinutes(20)]);

    $run = maacRun($agent, ['status' => RunStatus::Running, 'started_at' => now()->subHours(1)]);
    ToolCall::factory()->for($run)->create(['status' => ToolCallStatus::Completed, 'requested_at' => now()->subHours(1)]);
    ToolCall::factory()->for($run)->create(['status' => ToolCallStatus::Failed, 'requested_at' => now()->subHours(1)]);

    $result = app(OperationalMonitor::class)->forTeam($team);
    $m = $result['metrics'];

    expect($m['totalRuns'])->toBe(5)
        ->and($m['failedRuns'])->toBe(1)
        ->and($m['expiredRuns'])->toBe(1)
        ->and($m['waitingRuns'])->toBe(1)
        ->and($m['avgLatencyMs'])->toBe(2000)
        ->and($m['errorRate'])->toBe(20.0)
        ->and($m['toolFailureRate'])->toBe(50.0);

    $titles = collect($result['alerts'])->pluck('title');

    expect($result['alerts'][0]['sev'])->toBe('high')
        ->and($titles->contains(fn (string $t): bool => str_contains($t, 'failed')))->toBeTrue()
        ->and($titles->contains(fn (string $t): bool => str_contains($t, 'waiting')))->toBeTrue()
        ->and($titles->contains(fn (string $t): bool => str_contains($t, 'expired')))->toBeTrue();
});

test('a recently revoked credential raises an alert', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    Credential::factory()->for($application)->revoked()->create(['status' => CredentialStatus::Revoked, 'revoked_at' => now()->subHour()]);

    $alerts = collect(app(OperationalMonitor::class)->forTeam($team)['alerts']);

    expect($alerts->pluck('title'))->toContain('Credential revoked');
});

test('pending approvals raise a low-severity alert', function () {
    [, $team] = ownerAndTeam();
    ApprovalRequest::factory()->for($team)->create();

    $alert = collect(app(OperationalMonitor::class)->forTeam($team)['alerts'])->firstWhere('sev', 'low');

    expect($alert['title'])->toContain('awaiting approval');
});

test('cost anomaly is detected when today exceeds the trailing average', function () {
    [, $team] = ownerAndTeam();
    $agent = maacAgent($team);

    maacRun($agent, ['cost' => 1, 'started_at' => now()->subDays(2)]);
    maacRun($agent, ['cost' => 10, 'started_at' => now()->subHours(1)]);

    $result = app(OperationalMonitor::class)->forTeam($team);

    expect($result['metrics']['costAnomaly'])->toBeTrue()
        ->and(collect($result['alerts'])->pluck('title'))->toContain('Cost anomaly detected');
});

test('metrics and alerts are empty for a team with no activity', function () {
    [, $team] = ownerAndTeam();

    $operational = app(OperationalMonitor::class)->forTeam($team);
    $metrics = app(RunMetrics::class)->forTeam($team);

    expect($operational['metrics']['errorRate'])->toBe(0.0)
        ->and($operational['metrics']['toolFailureRate'])->toBe(0.0)
        ->and($operational['metrics']['avgLatencyMs'])->toBe(0)
        ->and($operational['metrics']['costAnomaly'])->toBeFalse()
        ->and($operational['alerts'])->toBe([])
        ->and($metrics['stats']['runsToday'])->toBe(0)
        ->and($metrics['topAgents'])->toBe([]);
});
