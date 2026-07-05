<?php

use App\Enums\AgentStatus;
use App\Enums\ApprovalType;
use App\Enums\Environment;
use App\Enums\LlmStatus;
use App\Enums\RunStatus;
use App\Enums\Sensitivity;
use App\Enums\TeamRole;
use App\Enums\TraceEventType;
use App\Enums\VaultSecretKind;
use App\Models\ApprovalRequest;
use App\Models\LlmProvider;
use App\Models\ModelRoutingPolicy;
use App\Models\SsoConnection;
use App\Models\User;
use App\Support\Runtime\AgentRunner;
use App\Support\Secrets\Contracts\SecretVault;
use Illuminate\Support\Facades\Http;

/**
 * Phase 6G end-to-end proof: a single scenario that exercises enterprise SSO role
 * mapping, vault-backed secret rotation through the runtime, advanced model
 * routing with fail-over, human-in-the-loop runtime approval, break-glass
 * incident response, and the signed audit export — the Phase 6G acceptance
 * criteria, end to end.
 */
test('the enterprise hardening surfaces work together end to end', function () {
    [$founder, $team] = ownerAndTeam();

    // 1. Enterprise SSO: an admin registers an identity connection that maps the
    //    "platform-admins" group to the Admin team role.
    $connection = SsoConnection::factory()->for($team)->withMappings([
        ['group' => 'platform-admins', 'team_role' => 'admin'],
    ])->create([
        'authorize_url' => 'https://idp.example.com/authorize',
        'token_url' => 'https://idp.example.com/token',
        'userinfo_url' => 'https://idp.example.com/userinfo',
    ]);

    Http::preventStrayRequests();
    Http::fake([
        'idp.example.com/token' => Http::response(['access_token' => 'at-e2e']),
        'idp.example.com/userinfo' => Http::response([
            'sub' => 'idp-user-1',
            'email' => 'newadmin@milaha.com',
            'name' => 'New Admin',
            'groups' => ['platform-admins'],
        ]),
    ]);

    $this->withSession(['sso.state' => 'e2e-state'])
        ->get(route('sso.callback', ['ssoConnection' => $connection->slug, 'state' => 'e2e-state', 'code' => 'e2e-code']))
        ->assertRedirect();

    $admin = User::firstWhere('email', 'newadmin@milaha.com');
    expect($admin)->not->toBeNull()
        ->and($admin->teamRole($team))->toBe(TeamRole::Admin)
        ->and($team->auditEvents()->where('action', 'sso.provisioned')->exists())->toBeTrue();

    // 2. Vault-backed secret rotation: bind the agent's model key to the vault and
    //    prove the runtime resolves it — then rotate and prove the new value wins.
    $agent = maaccAgent($team, ['status' => AgentStatus::Published, 'sensitivity' => Sensitivity::Public]);
    $vault = app(SecretVault::class);
    $secret = $vault->store($team, VaultSecretKind::LlmKey->reference($agent->llmProvider->slug), 'Model key', VaultSecretKind::LlmKey, 'sk-original', $admin);
    $agent->llmProvider->update(['vault_secret_id' => $secret->id]);
    $application = $agent->project->application;

    $fake = bindFakeRouter()->textThen('first');
    app(AgentRunner::class)->start($agent->fresh(), $application, Environment::Production, 'go', null);
    expect($fake->requests[0]->apiKey)->toBe('sk-original');

    $vault->rotate($secret, 'sk-rotated');
    $fake = bindFakeRouter()->textThen('second');
    app(AgentRunner::class)->start($agent->fresh(), $application, Environment::Production, 'go again', null);
    expect($fake->requests[0]->apiKey)->toBe('sk-rotated')
        ->and($secret->fresh()->version)->toBe(2);

    // 3. Advanced model routing: a cost policy with a fallback fails over when the
    //    primary model errors mid-run.
    $agent->llmProvider->update(['input_cost' => 0.1, 'output_cost' => 0.1]);
    $fallback = LlmProvider::factory()->for($team)->create(['input_cost' => 9, 'output_cost' => 9]);
    ModelRoutingPolicy::factory()->for($team)->for($agent)->costOptimized()->create(['fallback_provider_ids' => [$fallback->id]]);

    $fake = bindFakeRouter()->throwThen('primary down')->textThen('recovered');
    $routed = app(AgentRunner::class)->start($agent->fresh(), $application, Environment::Production, 'route me', null);
    expect($routed->status)->toBe(RunStatus::Completed)
        ->and($routed->llm_provider_id)->toBe($fallback->id)
        ->and($routed->traceEvents()->where('type', TraceEventType::ModelFailover)->exists())->toBeTrue();

    // 4. Human-in-the-loop runtime approval: flag the agent, invoke, pause, and let
    //    the admin approve to completion.
    $agent->update(['requires_runtime_approval' => true]);
    bindFakeRouter()->textThen('approved run');
    $paused = app(AgentRunner::class)->start($agent->fresh(), $application, Environment::Production, 'sensitive', null);
    expect($paused->status)->toBe(RunStatus::RequiresApproval);

    $approval = ApprovalRequest::where('subject_id', $paused->id)->where('type', ApprovalType::RuntimeAction)->firstOrFail();
    $this->actingAs($founder)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $approval->id]))
        ->assertRedirect();
    expect($paused->fresh()->status)->toBe(RunStatus::Completed);

    // 5. Break-glass incident response: freeze the application, prove runs are
    //    blocked, then lift the freeze.
    $this->actingAs($founder)
        ->post(route('incidents.store', ['current_team' => $team->slug]), [
            'action' => 'freeze_application',
            'target' => $application->slug,
            'reason' => 'E2E incident drill.',
        ])
        ->assertRedirect();

    $agent->update(['requires_runtime_approval' => false]);
    bindFakeRouter()->textThen('should not run');
    $frozenRun = app(AgentRunner::class)->start($agent->fresh(), $application->fresh(), Environment::Production, 'blocked', null);
    expect($frozenRun->status)->toBe(RunStatus::Failed)
        ->and($frozenRun->failure_reason)->toBe('runtime_frozen');

    // Disable the model as a second break-glass control for the audit trail.
    $this->actingAs($founder)
        ->post(route('incidents.store', ['current_team' => $team->slug]), [
            'action' => 'disable_model',
            'target' => $fallback->slug,
            'reason' => 'E2E model containment.',
        ])
        ->assertRedirect();
    expect($fallback->fresh()->status)->toBe(LlmStatus::Blocked);

    // 6. Enterprise audit export: the signed export captures the whole incident and
    //    identity trail for a security reviewer.
    $export = $this->actingAs($founder)
        ->get(route('audit-export', ['current_team' => $team->slug]))
        ->assertOk()
        ->json();

    $actions = collect($export['events'])->pluck('action');
    expect($export['manifest']['checksum'])->toBeString()
        ->and($actions)->toContain('sso.provisioned')
        ->and($actions)->toContain('incident.freeze_application')
        ->and($actions)->toContain('incident.disable_model');
});
