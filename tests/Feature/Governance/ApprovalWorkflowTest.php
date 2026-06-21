<?php

use App\Actions\Maac\ApproveApprovalRequest;
use App\Enums\AgentStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\Environment;
use App\Enums\LlmStatus;
use App\Enums\MaacRole;
use App\Models\Application;
use App\Models\ApprovalRequest;
use App\Models\Credential;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\ToolContract;
use App\Support\Governance\ApprovalManager;

test('a developer can request approval for a tool contract', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $developer = projectRoleUser($team, $project, MaacRole::Developer);
    $tool = ToolContract::factory()->for($team)->create(['application_id' => $application->id]);

    $this->actingAs($developer)
        ->post(route('approvals.store', ['current_team' => $team->slug]), [
            'type' => ApprovalType::ToolContract->value,
            'subject' => $tool->slug,
        ])
        ->assertRedirect();

    $request = ApprovalRequest::firstWhere('subject_id', $tool->id);

    expect($request)->not->toBeNull()
        ->and($request->type)->toBe(ApprovalType::ToolContract)
        ->and($request->status)->toBe(ApprovalStatus::Pending);
});

test('approving an agent publication request publishes the agent', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = maacAgent($team, ['status' => AgentStatus::Draft, 'version' => 'v1']);

    $this->actingAs($owner)->post(route('approvals.store', ['current_team' => $team->slug]), [
        'type' => ApprovalType::AgentPublication->value,
        'subject' => $agent->slug,
        'environment' => Environment::Production->value,
    ])->assertRedirect();

    $request = ApprovalRequest::firstWhere('type', ApprovalType::AgentPublication->value);

    $this->actingAs($owner)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $request->id]), ['note' => 'Looks good'])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($request->fresh()->decision_note)->toBe('Looks good')
        ->and($agent->fresh()->status)->toBe(AgentStatus::Published);
});

test('approving a tool contract request activates the contract', function () {
    [$owner, $team] = ownerAndTeam();
    $tool = ToolContract::factory()->for($team)->create(['status' => 'Pending']);
    $request = app(ApprovalManager::class)->requestToolContractApproval($tool, $owner);

    $this->actingAs($owner)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $request->id]))
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($tool->fresh()->status)->toBe('Active');
});

test('approving a model access request promotes the model into the environment', function () {
    [$owner, $team] = ownerAndTeam();
    $model = LlmProvider::factory()->for($team)->create(['environments' => ['development'], 'status' => LlmStatus::Approved]);
    $request = app(ApprovalManager::class)->requestModelAccess($model, $owner, Environment::Production);

    $this->actingAs($owner)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $request->id]))
        ->assertRedirect();

    expect($model->fresh()->environments)->toContain('production')
        ->and($model->fresh()->isAvailableIn('production'))->toBeTrue();
});

test('promoting a model already available in the environment is idempotent', function () {
    [$owner, $team] = ownerAndTeam();
    $model = LlmProvider::factory()->for($team)->create(['environments' => ['development', 'production']]);
    $request = app(ApprovalManager::class)->requestModelAccess($model, $owner, Environment::Production);

    app(ApproveApprovalRequest::class)->handle($request, $owner);

    expect($model->fresh()->environments)->toBe(['development', 'production']);
});

test('approving a credential change records the decision without side effects', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $credential = Credential::factory()->for($application)->create();
    $request = app(ApprovalManager::class)->requestCredentialChange($credential, $owner, 'rotation');

    app(ApproveApprovalRequest::class)->handle($request, $owner);

    expect($request->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($request->fresh()->type)->toBe(ApprovalType::CredentialChange);
});

test('approving requests whose subjects are missing applies no change', function () {
    [$owner, $team] = ownerAndTeam();

    foreach ([ApprovalType::AgentPublication, ApprovalType::ToolContract, ApprovalType::ModelAccess] as $type) {
        $request = ApprovalRequest::factory()->for($team)->create([
            'type' => $type,
            'subject_type' => null,
            'subject_id' => null,
            'environment' => null,
        ]);

        app(ApproveApprovalRequest::class)->handle($request, $owner);

        expect($request->fresh()->status)->toBe(ApprovalStatus::Approved);
    }
});

test('a request can be rejected with a note', function () {
    [$owner, $team] = ownerAndTeam();
    $request = ApprovalRequest::factory()->for($team)->create();

    $this->actingAs($owner)
        ->post(route('approvals.reject', ['current_team' => $team->slug, 'approvalRequest' => $request->id]), ['note' => 'Out of policy'])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(ApprovalStatus::Rejected)
        ->and($request->fresh()->decision_note)->toBe('Out of policy');
});

test('an already-decided request cannot be decided again', function () {
    [$owner, $team] = ownerAndTeam();
    $request = ApprovalRequest::factory()->for($team)->approved()->create();

    $this->actingAs($owner)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $request->id]))
        ->assertStatus(409);
});

test('a viewer cannot decide an approval request', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $viewer = projectRoleUser($team, $project, MaacRole::Viewer);
    $request = ApprovalRequest::factory()->for($team)->create();

    $this->actingAs($viewer)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $request->id]))
        ->assertForbidden();
});

test('a security reviewer can decide an approval request', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $reviewer = projectRoleUser($team, $project, MaacRole::SecurityReviewer);
    $request = ApprovalRequest::factory()->for($team)->create();

    $this->actingAs($reviewer)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $request->id]))
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(ApprovalStatus::Approved);
});

test('a plain member cannot request an approval', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);
    $tool = ToolContract::factory()->for($team)->create();

    $this->actingAs($member)
        ->post(route('approvals.store', ['current_team' => $team->slug]), [
            'type' => ApprovalType::ToolContract->value,
            'subject' => $tool->slug,
        ])
        ->assertForbidden();
});

test('requesting approval for an unknown subject returns 404', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('approvals.store', ['current_team' => $team->slug]), [
            'type' => ApprovalType::ModelAccess->value,
            'subject' => 'does-not-exist',
            'environment' => Environment::Production->value,
        ])
        ->assertNotFound();
});

test('approval manager is idempotent and supports subjectless requests', function () {
    [$owner, $team] = ownerAndTeam();
    $tool = ToolContract::factory()->for($team)->create();

    $first = app(ApprovalManager::class)->requestToolContractApproval($tool, $owner);
    $second = app(ApprovalManager::class)->requestToolContractApproval($tool, $owner);

    expect($first->id)->toBe($second->id)
        ->and(ApprovalRequest::where('subject_id', $tool->id)->count())->toBe(1);

    $subjectless = app(ApprovalManager::class)->open($team, ApprovalType::ModelAccess, null, ['title' => 'Region policy']);

    expect($subjectless->subject_id)->toBeNull()
        ->and($subjectless->isPending())->toBeTrue();
});

test('credential change request via the runtime endpoint resolves the credential', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $credential = Credential::factory()->for($application)->create();

    $this->actingAs($owner)->post(route('approvals.store', ['current_team' => $team->slug]), [
        'type' => ApprovalType::CredentialChange->value,
        'subject' => $credential->id,
        'environment' => Environment::Production->value,
        'change' => 'rotation',
    ])->assertRedirect();

    expect(ApprovalRequest::where('type', ApprovalType::CredentialChange->value)->where('subject_id', $credential->id)->exists())->toBeTrue();
});
