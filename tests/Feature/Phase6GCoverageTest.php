<?php

use App\Enums\AgentStatus;
use App\Enums\Environment;
use App\Enums\MaaccRole;
use App\Enums\RoutingStrategy;
use App\Enums\RunStatus;
use App\Enums\Sensitivity;
use App\Exceptions\Sdk\RuntimeRequestException;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\IncidentAction;
use App\Models\LlmProvider;
use App\Models\ModelRoutingPolicy;
use App\Models\Project;
use App\Models\SsoConnection;
use App\Models\VaultSecret;
use App\Support\Governance\AuditExporter;
use App\Support\Governance\IncidentGuard;
use App\Support\Runtime\AgentRunner;
use App\Support\Runtime\Routing\ModelRouter;

test('the enterprise policies authorize platform admins and reject plain members', function () {
    [$owner, $team] = ownerAndTeam();
    $member = teamMember($team);
    $secret = VaultSecret::factory()->for($team)->create();
    $connection = SsoConnection::factory()->for($team)->create();

    expect($owner->can('viewAny', VaultSecret::class))->toBeTrue()
        ->and($owner->can('create', VaultSecret::class))->toBeTrue()
        ->and($owner->can('update', $secret))->toBeTrue()
        ->and($owner->can('delete', $secret))->toBeTrue()
        ->and($member->can('viewAny', VaultSecret::class))->toBeFalse()
        ->and($member->can('create', VaultSecret::class))->toBeFalse()
        ->and($owner->can('viewAny', SsoConnection::class))->toBeTrue()
        ->and($owner->can('create', SsoConnection::class))->toBeTrue()
        ->and($owner->can('update', $connection))->toBeTrue()
        ->and($owner->can('delete', $connection))->toBeTrue()
        ->and($member->can('viewAny', SsoConnection::class))->toBeFalse()
        ->and($member->can('viewAny', IncidentAction::class))->toBeFalse()
        ->and($member->can('create', IncidentAction::class))->toBeFalse();
});

test('routing and incident policies honor the manage-agent and security-review permissions', function () {
    [$owner, $team] = ownerAndTeam();
    $member = teamMember($team);
    $project = Project::factory()->for(Application::factory()->for($team))->create();
    $developer = projectRoleUser($team, $project, MaaccRole::Developer);
    $reviewer = projectRoleUser($team, $project, MaaccRole::SecurityReviewer);
    $policy = ModelRoutingPolicy::factory()->for($team)->create();

    expect($owner->can('viewAny', ModelRoutingPolicy::class))->toBeTrue()
        ->and($member->can('viewAny', ModelRoutingPolicy::class))->toBeTrue()
        ->and($developer->can('create', ModelRoutingPolicy::class))->toBeTrue()
        ->and($developer->can('update', $policy))->toBeTrue()
        ->and($developer->can('delete', $policy))->toBeTrue()
        ->and($reviewer->can('viewAny', IncidentAction::class))->toBeTrue()
        ->and($reviewer->can('create', IncidentAction::class))->toBeTrue();
});

test('approving or denying a runtime decision on a settled run is a safe no-op', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team, ['status' => AgentStatus::Published]);
    $run = maaccRun($agent, ['status' => RunStatus::Completed]);

    $runner = app(AgentRunner::class);
    $runner->approveRuntime($run);
    $denied = $runner->denyRuntime($run);

    expect($run->fresh()->status)->toBe(RunStatus::Completed)
        ->and($denied->status)->toBe(RunStatus::Completed);
});

test('the incident guard rejects a run against a frozen application', function () {
    [, $team] = ownerAndTeam();
    $frozen = Application::factory()->for($team)->create(['runtime_frozen_at' => now()]);
    $open = Application::factory()->for($team)->create();
    $guard = app(IncidentGuard::class);

    $guard->assert($open);

    expect(fn () => $guard->assert($frozen))->toThrow(RuntimeRequestException::class);
});

test('a tool result submitted to a frozen application is rejected', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team, ['status' => AgentStatus::Published]);
    $application = $agent->project->application;
    $application->update(['runtime_frozen_at' => now()]);
    $run = maaccRun($agent, ['status' => RunStatus::WaitingForClient, 'environment' => Environment::Production]);

    expect(fn () => app(AgentRunner::class)->acceptToolResult($run, 'tc', []))
        ->toThrow(RuntimeRequestException::class);
});

test('failover gives up when the run has no routing chain or the model is gone', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team, ['status' => AgentStatus::Published, 'sensitivity' => Sensitivity::Public]);

    $base = [
        'status' => RunStatus::Running,
        'environment' => Environment::Production,
        'expires_at' => now()->addHour(),
    ];

    // No routing chain in state.
    $noChain = maaccRun($agent, [...$base, 'state' => ['messages' => [['role' => 'user', 'content' => 'hi']], 'steps' => 0]]);
    bindFakeRouter()->throwThen('down');
    expect(app(AgentRunner::class)->drive($noChain)->failure_reason)->toBe('model_error');

    // Chain references a model that no longer exists.
    $goneModel = maaccRun($agent, [...$base, 'state' => [
        'messages' => [['role' => 'user', 'content' => 'hi']],
        'steps' => 0,
        'routing' => ['chain' => ['00000000-0000-0000-0000-000000000000'], 'tried' => []],
    ]]);
    bindFakeRouter()->throwThen('down');
    expect(app(AgentRunner::class)->drive($goneModel)->failure_reason)->toBe('model_error');
});

test('the router reports no eligible model when every candidate exceeds the latency target', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    AgentRun::factory()->count(5)->create([
        'llm_provider_id' => $agent->llm_provider_id,
        'environment' => Environment::Production,
        'status' => RunStatus::Completed,
        'failure_reason' => null,
        'latency_ms' => 9000,
        'started_at' => now(),
    ]);
    ModelRoutingPolicy::factory()->for($team)->for($agent)->create(['max_latency_ms' => 1000, 'fallback_provider_ids' => []]);
    $run = maaccRun($agent, ['environment' => Environment::Production, 'sensitivity' => Sensitivity::Public]);

    $decision = app(ModelRouter::class)->select($run->load(['agent.routingPolicy', 'agent.llmProvider']));

    expect($decision->provider)->toBeNull()
        ->and($decision->rationale)->toContain('No candidate')
        ->and($decision->considered[0]->reason)->toContain('latency target');
});

test('the latency-optimized strategy selects the fastest eligible model', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $fast = LlmProvider::factory()->for($team)->create();

    foreach ([[$agent->llm_provider_id, 5000], [$fast->id, 200]] as [$providerId, $latency]) {
        AgentRun::factory()->count(5)->create([
            'llm_provider_id' => $providerId,
            'environment' => Environment::Production,
            'status' => RunStatus::Completed,
            'failure_reason' => null,
            'latency_ms' => $latency,
            'started_at' => now(),
        ]);
    }

    ModelRoutingPolicy::factory()->for($team)->for($agent)->create([
        'strategy' => RoutingStrategy::LatencyOptimized,
        'fallback_provider_ids' => [$fast->id],
    ]);
    $run = maaccRun($agent, ['environment' => Environment::Production, 'sensitivity' => Sensitivity::Public]);

    $decision = app(ModelRouter::class)->select($run->load(['agent.routingPolicy', 'agent.llmProvider']));

    expect($decision->provider->is($fast))->toBeTrue();
});

test('the audit export honors the date-range and actor filters', function () {
    [$owner, $team] = ownerAndTeam();
    AuditEvent::factory()->for($team)->create(['actor_user_id' => $owner->id, 'created_at' => now()->subDays(1)]);
    AuditEvent::factory()->for($team)->create(['actor_user_id' => null, 'created_at' => now()->subDays(30)]);

    $export = app(AuditExporter::class)->export($team, [
        'from' => now()->subDays(5)->toDateTimeString(),
        'to' => now()->toDateTimeString(),
        'action' => null,
        'actor' => $owner->id,
    ]);

    expect($export['manifest']['count'])->toBe(1)
        ->and($export['manifest']['filters'])->toHaveKey('actor');
});

test('the enterprise models expose their creator, subject, and identity relations', function () {
    [$owner, $team] = ownerAndTeam();
    $secret = VaultSecret::factory()->for($team)->create(['created_by' => $owner->id]);
    $policy = ModelRoutingPolicy::factory()->for($team)->create(['created_by' => $owner->id]);
    $connection = SsoConnection::factory()->for($team)->create(['created_by' => $owner->id]);
    $incident = IncidentAction::factory()->for($team)->create([
        'subject_type' => $connection->getMorphClass(),
        'subject_id' => $connection->id,
    ]);

    expect($secret->creator->is($owner))->toBeTrue()
        ->and($policy->creator->is($owner))->toBeTrue()
        ->and($connection->creator->is($owner))->toBeTrue()
        ->and($incident->subject->is($connection))->toBeTrue();
});
