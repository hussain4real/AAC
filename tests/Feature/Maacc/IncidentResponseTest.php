<?php

use App\Enums\AgentStatus;
use App\Enums\CredentialStatus;
use App\Enums\Environment;
use App\Enums\LlmStatus;
use App\Enums\McpConnectorStatus;
use App\Enums\RunStatus;
use App\Enums\Sensitivity;
use App\Enums\WebhookEndpointStatus;
use App\Models\Application;
use App\Models\Credential;
use App\Models\IncidentAction;
use App\Models\LlmProvider;
use App\Models\McpConnector;
use App\Models\WebhookEndpoint;
use App\Support\Runtime\AgentRunner;
use Inertia\Testing\AssertableInertia as Assert;

test('the incidents console page renders', function () {
    [$owner, $team] = ownerAndTeam();

    $this->withoutVite()
        ->actingAs($owner)
        ->get(route('incidents', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('maacc/incidents'));
});

test('an operator can disable a model as a break-glass control', function () {
    [$owner, $team] = ownerAndTeam();
    $model = LlmProvider::factory()->for($team)->create(['status' => LlmStatus::Approved]);

    $this->actingAs($owner)
        ->post(route('incidents.store', ['current_team' => $team->slug]), [
            'action' => 'disable_model',
            'target' => $model->slug,
            'reason' => 'Vendor outage causing bad outputs.',
        ])
        ->assertRedirect();

    $incident = IncidentAction::firstWhere('type', 'disable_model');

    expect($model->fresh()->status)->toBe(LlmStatus::Blocked)
        ->and($incident)->not->toBeNull()
        ->and($incident->reason)->toBe('Vendor outage causing bad outputs.')
        ->and($incident->actor_user_id)->toBe($owner->getAuthIdentifier())
        ->and($incident->subject_label)->toBe($model->name)
        // The break-glass action writes a high-severity audit event.
        ->and($team->auditEvents()->where('action', 'incident.disable_model')->exists())->toBeTrue();
});

test('an operator can revoke a credential, shut down a connector, and suspend a webhook', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $credential = Credential::factory()->for($application)->create(['status' => CredentialStatus::Active]);
    $connector = McpConnector::factory()->for($team)->create(['status' => McpConnectorStatus::Active]);
    $webhook = WebhookEndpoint::factory()->for($application)->create(['status' => WebhookEndpointStatus::Active]);

    $post = fn (array $payload) => $this->actingAs($owner)
        ->post(route('incidents.store', ['current_team' => $team->slug]), $payload)
        ->assertRedirect();

    $post(['action' => 'revoke_credential', 'target' => $credential->id, 'reason' => 'Leaked secret.']);
    $post(['action' => 'shutdown_connector', 'target' => $connector->slug, 'reason' => 'Connector compromised.']);
    $post(['action' => 'suspend_webhook', 'target' => $webhook->id, 'reason' => 'Receiver flooded.']);

    expect($credential->fresh()->status)->toBe(CredentialStatus::Revoked)
        ->and($connector->fresh()->status)->toBe(McpConnectorStatus::Disabled)
        ->and($webhook->fresh()->status)->toBe(WebhookEndpointStatus::Disabled)
        ->and(IncidentAction::count())->toBe(3);
});

test('freezing an application blocks new runs and lifting it restores them', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = maaccAgent($team, ['status' => AgentStatus::Published, 'sensitivity' => Sensitivity::Public]);
    $application = $agent->project->application;

    $this->actingAs($owner)
        ->post(route('incidents.store', ['current_team' => $team->slug]), [
            'action' => 'freeze_application',
            'target' => $application->slug,
            'reason' => 'Active incident under investigation.',
        ])
        ->assertRedirect();

    expect($application->fresh()->isRuntimeFrozen())->toBeTrue();

    // A run started against the frozen application is halted with a controlled failure.
    bindFakeRouter()->textThen('should not run');
    $frozenRun = app(AgentRunner::class)->start($agent->fresh(), $application->fresh(), Environment::Production, 'go', null);
    expect($frozenRun->status)->toBe(RunStatus::Failed)
        ->and($frozenRun->failure_reason)->toBe('runtime_frozen');

    // Lifting the freeze marks the originating freeze reverted and resumes runs.
    $this->actingAs($owner)
        ->post(route('incidents.store', ['current_team' => $team->slug]), [
            'action' => 'lift_freeze',
            'target' => $application->slug,
            'reason' => 'Incident resolved.',
        ])
        ->assertRedirect();

    expect($application->fresh()->isRuntimeFrozen())->toBeFalse()
        ->and(IncidentAction::where('type', 'freeze_application')->whereNotNull('reverted_at')->exists())->toBeTrue();

    bindFakeRouter()->textThen('runs again');
    $resumedRun = app(AgentRunner::class)->start($agent->fresh(), $application->fresh(), Environment::Production, 'go', null);
    expect($resumedRun->status)->toBe(RunStatus::Completed);
});

test('a plain member cannot trigger an incident control', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);
    $model = LlmProvider::factory()->for($team)->create();

    $this->actingAs($member)
        ->post(route('incidents.store', ['current_team' => $team->slug]), [
            'action' => 'disable_model',
            'target' => $model->slug,
            'reason' => 'Trying to break things.',
        ])
        ->assertForbidden();
});

test('incident controls validate the action and require a reason', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('incidents.store', ['current_team' => $team->slug]), [
            'action' => 'not-a-real-action',
            'target' => '',
            'reason' => 'x',
        ])
        ->assertSessionHasErrors(['action', 'target', 'reason']);
});

test('the incident response page exposes the incident timeline', function () {
    [$owner, $team] = ownerAndTeam();
    IncidentAction::factory()->for($team)->create([
        'type' => 'disable_model',
        'subject_label' => 'GPT-4o',
        'reason' => 'Outage',
        'actor_label' => 'Sec Team',
    ]);

    $this->actingAs($owner)
        ->get(route('incidents', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maacc.incidents', 1)
            ->where('maacc.incidents.0.typeLabel', 'Disable Model')
            ->where('maacc.incidents.0.severity', 'high')
            ->where('maacc.incidents.0.subject', 'GPT-4o'));
});
