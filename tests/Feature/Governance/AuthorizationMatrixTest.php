<?php

use App\Enums\MaacRole;
use App\Models\Application;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\GovernanceSetting;
use App\Models\Project;
use App\Models\QuotaLimit;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Build a user holding the given MAAC role and return them with their team.
 *
 * @return array{0: User, 1: Team}
 */
function userWithRole(MaacRole $role): array
{
    [$owner, $team] = ownerAndTeam();

    if ($role === MaacRole::PlatformAdmin) {
        return [$owner, $team];
    }

    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();

    return [projectRoleUser($team, $project, $role), $team];
}

test('governance authorization matrix holds for every MAAC role', function (
    MaacRole $role,
    bool $canManageSettings,
    bool $canManageQuota,
    bool $canDecide,
    bool $canRequest,
    bool $canAudit,
) {
    [$user, $team] = userWithRole($role);
    $gate = Gate::forUser($user);
    $approval = ApprovalRequest::factory()->for($team)->create();
    $settings = GovernanceSetting::forTeam($team);

    expect($gate->allows('update', $settings))->toBe($canManageSettings)
        ->and($gate->allows('create', QuotaLimit::class))->toBe($canManageQuota)
        ->and($gate->allows('decide', $approval))->toBe($canDecide)
        ->and($gate->allows('create', ApprovalRequest::class))->toBe($canRequest)
        ->and($gate->allows('viewAny', AuditEvent::class))->toBe($canAudit);
})->with([
    //                       role,                      settings, quota, decide, request, audit
    'platform admin' => [MaacRole::PlatformAdmin, true, true, true, true, true],
    'project owner' => [MaacRole::ProjectOwner, false, false, true, true, false],
    'developer' => [MaacRole::Developer, false, false, false, true, false],
    'viewer' => [MaacRole::Viewer, false, false, false, false, false],
    'auditor' => [MaacRole::Auditor, false, false, false, false, true],
    'security reviewer' => [MaacRole::SecurityReviewer, false, false, true, false, true],
]);

test('approval queue visibility requires a team and team-less users cannot request', function () {
    [$owner, $team] = ownerAndTeam();

    expect(Gate::forUser($owner)->allows('viewAny', ApprovalRequest::class))->toBeTrue()
        ->and($team->governanceSetting)->toBeNull();

    GovernanceSetting::factory()->for($team)->create();
    expect($team->fresh()->governanceSetting)->not->toBeNull();

    $teamless = User::factory()->create();
    $teamless->forceFill(['current_team_id' => null])->save();

    expect(Gate::forUser($teamless->fresh())->allows('create', ApprovalRequest::class))->toBeFalse();
});

test('team members can view governance settings but outsiders cannot', function () {
    [, $team] = ownerAndTeam();
    $settings = GovernanceSetting::factory()->for($team)->create();
    $member = teamMember($team);
    $outsider = User::factory()->create();

    expect(Gate::forUser($member)->allows('view', $settings))->toBeTrue()
        ->and(Gate::forUser($outsider)->allows('view', $settings))->toBeFalse();
});

test('a platform admin can create, update, and delete quotas', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('quotas.store', ['current_team' => $team->slug]), ['scope' => 'platform', 'max_runs_per_day' => 100])
        ->assertRedirect();

    $quota = QuotaLimit::firstWhere('team_id', $team->id);
    expect($quota)->not->toBeNull();

    $this->actingAs($owner)
        ->put(route('quotas.update', ['current_team' => $team->slug, 'quotaLimit' => $quota->id]), ['enabled' => false])
        ->assertRedirect();
    expect($quota->fresh()->enabled)->toBeFalse();

    $this->actingAs($owner)
        ->delete(route('quotas.destroy', ['current_team' => $team->slug, 'quotaLimit' => $quota->id]))
        ->assertRedirect();
    expect(QuotaLimit::find($quota->id))->toBeNull();
});

test('quota creation requires a run or token limit', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('quotas.store', ['current_team' => $team->slug]), ['scope' => 'platform'])
        ->assertSessionHasErrors(['max_runs_per_day', 'max_tokens_per_day']);
});

test('a non-admin cannot manage quotas', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);
    $quota = QuotaLimit::factory()->for($team)->create();

    $this->actingAs($member)
        ->post(route('quotas.store', ['current_team' => $team->slug]), ['scope' => 'platform', 'max_runs_per_day' => 5])
        ->assertForbidden();

    $this->actingAs($member)
        ->put(route('quotas.update', ['current_team' => $team->slug, 'quotaLimit' => $quota->id]), ['enabled' => false])
        ->assertForbidden();

    $this->actingAs($member)
        ->delete(route('quotas.destroy', ['current_team' => $team->slug, 'quotaLimit' => $quota->id]))
        ->assertForbidden();
});
