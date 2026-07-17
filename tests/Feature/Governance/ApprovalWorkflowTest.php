<?php

use App\Actions\Maacc\ApproveApprovalRequest;
use App\Enums\AgentStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\Environment;
use App\Enums\LlmStatus;
use App\Enums\MaaccRole;
use App\Enums\TeamRole;
use App\Exceptions\ApprovalBlockedException;
use App\Models\Application;
use App\Models\ApprovalRequest;
use App\Models\Credential;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\Team;
use App\Models\ToolContract;
use App\Models\User;
use App\Support\Governance\ApprovalManager;
use Illuminate\Support\Facades\Hash;

function approvalReviewer(Team $team): User
{
    $reviewer = User::factory()->create();
    $team->members()->attach($reviewer, ['role' => TeamRole::Admin->value]);
    $reviewer->switchTeam($team);

    return $reviewer;
}

test('a developer can request approval for a tool contract', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $developer = projectRoleUser($team, $project, MaaccRole::Developer);
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
    $reviewer = approvalReviewer($team);
    $agent = maaccAgent($team, ['status' => AgentStatus::Draft, 'version' => 'v1']);

    $this->actingAs($owner)->post(route('approvals.store', ['current_team' => $team->slug]), [
        'type' => ApprovalType::AgentPublication->value,
        'subject' => $agent->slug,
        'environment' => Environment::Production->value,
    ])->assertRedirect();

    $request = ApprovalRequest::firstWhere('type', ApprovalType::AgentPublication->value);

    $this->actingAs($reviewer)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $request->id]), ['note' => 'Looks good'])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($request->fresh()->decision_note)->toBe('Looks good')
        ->and($agent->fresh()->status)->toBe(AgentStatus::Published)
        ->and($agent->versions()->count())->toBe(1);

    $this->actingAs($reviewer)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $request->id]))
        ->assertRedirect();

    expect($agent->versions()->count())->toBe(1);
});

test('approving a tool contract request activates the contract', function () {
    [$owner, $team] = ownerAndTeam();
    $reviewer = approvalReviewer($team);
    $tool = ToolContract::factory()->for($team)->create(['status' => 'Pending']);
    $request = app(ApprovalManager::class)->requestToolContractApproval($tool, $owner);

    $this->actingAs($reviewer)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $request->id]))
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($tool->fresh()->status)->toBe('Active');
});

test('approving a model access request promotes the model into the environment', function () {
    [$owner, $team] = ownerAndTeam();
    $reviewer = approvalReviewer($team);
    $model = LlmProvider::factory()->for($team)->create(['environments' => ['development'], 'status' => LlmStatus::Approved]);
    $request = app(ApprovalManager::class)->requestModelAccess($model, $owner, Environment::Production);

    $this->actingAs($reviewer)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $request->id]))
        ->assertRedirect();

    expect($model->fresh()->environments)->toContain('production')
        ->and($model->fresh()->isAvailableIn('production'))->toBeTrue();
});

test('promoting a model already available in the environment is idempotent', function () {
    [$owner, $team] = ownerAndTeam();
    $reviewer = approvalReviewer($team);
    $model = LlmProvider::factory()->for($team)->create(['environments' => ['development', 'production']]);
    $request = app(ApprovalManager::class)->requestModelAccess($model, $owner, Environment::Production);

    app(ApproveApprovalRequest::class)->handle($request, $reviewer);

    expect($model->fresh()->environments)->toBe(['development', 'production']);
});

test('a model change invalidates its pending promotion approval', function () {
    [$owner, $team] = ownerAndTeam();
    $reviewer = approvalReviewer($team);
    $model = LlmProvider::factory()->for($team)->create(['environments' => ['development']]);
    $request = app(ApprovalManager::class)->requestModelAccess($model, $owner, Environment::Production);

    $model->update(['code' => 'changed/model']);

    expect(fn () => app(ApproveApprovalRequest::class)->handle($request, $reviewer))
        ->toThrow(ApprovalBlockedException::class);

    expect($request->fresh()->status)->toBe(ApprovalStatus::Pending)
        ->and($model->fresh()->environments)->toBe(['development']);
});

test('a requester cannot approve their own change', function () {
    [$owner, $team] = ownerAndTeam();
    $tool = ToolContract::factory()->for($team)->create(['status' => 'Pending']);
    $request = app(ApprovalManager::class)->requestToolContractApproval($tool, $owner);

    expect(fn () => app(ApproveApprovalRequest::class)->handle($request, $owner))
        ->toThrow(ApprovalBlockedException::class);

    expect($request->fresh()->status)->toBe(ApprovalStatus::Pending)
        ->and($tool->fresh()->status)->toBe('Pending');
});

test('approving a staged credential rotation applies its secret exactly once', function () {
    [$owner, $team] = ownerAndTeam();
    $reviewer = approvalReviewer($team);
    $application = Application::factory()->for($team)->create();
    $credential = Credential::factory()->for($application)->create();
    $originalHash = $credential->secret_hash;
    $secret = Credential::generateSecret();
    $request = app(ApprovalManager::class)->requestCredentialChange($credential, $owner, 'rotation', $secret);

    app(ApproveApprovalRequest::class)->handle($request, $reviewer);

    expect($request->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($request->fresh()->type)->toBe(ApprovalType::CredentialChange)
        ->and($request->fresh()->encrypted_payload)->toBeNull()
        ->and($credential->fresh()->secret_hash)->not->toBe($originalHash)
        ->and(Hash::check($secret, $credential->fresh()->secret_hash))->toBeTrue();
});

test('a staged credential rotation is rejected after the live credential changes', function () {
    [$owner, $team] = ownerAndTeam();
    $reviewer = approvalReviewer($team);
    $application = Application::factory()->for($team)->create();
    $credential = Credential::factory()->for($application)->create();
    $request = app(ApprovalManager::class)->requestCredentialChange(
        $credential,
        $owner,
        'rotation',
        Credential::generateSecret(),
    );

    $credential->fillSecret(Credential::generateSecret());
    $credential->save();

    expect(fn () => app(ApproveApprovalRequest::class)->handle($request, $reviewer))
        ->toThrow(ApprovalBlockedException::class);

    expect($request->fresh()->status)->toBe(ApprovalStatus::Pending);
});

test('approving requests whose subjects are missing fails closed', function () {
    [$owner, $team] = ownerAndTeam();

    foreach ([ApprovalType::AgentPublication, ApprovalType::ToolContract, ApprovalType::ModelAccess] as $type) {
        $request = ApprovalRequest::factory()->for($team)->create([
            'type' => $type,
            'subject_type' => null,
            'subject_id' => null,
            'environment' => null,
        ]);

        expect(fn () => app(ApproveApprovalRequest::class)->handle($request, $owner))
            ->toThrow(ApprovalBlockedException::class);

        expect($request->fresh()->status)->toBe(ApprovalStatus::Pending);
    }
});

test('a request can be rejected with a note', function () {
    [$owner, $team] = ownerAndTeam();
    $tool = ToolContract::factory()->for($team)->create();
    $request = ApprovalRequest::factory()->for($team)->for($tool, 'subject')->create([
        'type' => ApprovalType::ToolContract,
    ]);

    $this->actingAs($owner)
        ->post(route('approvals.reject', ['current_team' => $team->slug, 'approvalRequest' => $request->id]), ['note' => 'Out of policy'])
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(ApprovalStatus::Rejected)
        ->and($request->fresh()->decision_note)->toBe('Out of policy');
});

test('an already-decided request is handled idempotently', function () {
    [$owner, $team] = ownerAndTeam();
    $request = ApprovalRequest::factory()->for($team)->approved()->create();

    $this->actingAs($owner)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $request->id]))
        ->assertRedirect();

    expect($request->fresh()->status)->toBe(ApprovalStatus::Approved);
});

test('a viewer cannot decide an approval request', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $viewer = projectRoleUser($team, $project, MaaccRole::Viewer);
    $request = ApprovalRequest::factory()->for($team)->create();

    $this->actingAs($viewer)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $request->id]))
        ->assertForbidden();
});

test('a security reviewer can decide an approval request', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $reviewer = projectRoleUser($team, $project, MaaccRole::SecurityReviewer);
    $tool = ToolContract::factory()->for($team)->create();
    $request = ApprovalRequest::factory()->for($team)->for($tool, 'subject')->create([
        'type' => ApprovalType::ToolContract,
    ]);

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

test('approval manager allows only one pending request per subject version', function () {
    [$owner, $team] = ownerAndTeam();
    $tool = ToolContract::factory()->for($team)->create();

    $first = app(ApprovalManager::class)->requestToolContractApproval($tool, $owner);
    $second = app(ApprovalManager::class)->requestToolContractApproval($tool, $owner);

    expect($first->id)->toBe($second->id)
        ->and(ApprovalRequest::where('subject_id', $tool->id)->count())->toBe(1);
});

test('credential changes cannot bypass staging through the generic approval endpoint', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $credential = Credential::factory()->for($application)->create();

    $this->actingAs($owner)->post(route('approvals.store', ['current_team' => $team->slug]), [
        'type' => ApprovalType::CredentialChange->value,
        'subject' => $credential->id,
        'environment' => Environment::Production->value,
        'change' => 'rotation',
    ])->assertStatus(422);

    expect(ApprovalRequest::where('type', ApprovalType::CredentialChange->value)->where('subject_id', $credential->id)->exists())->toBeFalse();
});
