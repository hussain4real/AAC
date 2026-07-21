<?php

use App\Enums\MaaccPermission;
use App\Enums\MaaccRole;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\ProjectMember;

test('a project owner can assign change certify and revoke project access with audit evidence', function () {
    [, $team] = ownerAndTeam();
    $project = Project::factory()->for(Application::factory()->for($team))->create();
    $owner = projectRoleUser($team, $project, MaaccRole::ProjectOwner);
    $subject = teamMember($team);
    $expiresAt = now()->addMonth()->startOfMinute();

    $this->actingAs($owner)
        ->postJson(route('projects.members.store', [$team->slug, $project]), [
            'user_id' => $subject->id,
            'role' => MaaccRole::Developer->value,
            'expires_at' => $expiresAt->toIso8601String(),
            'reason' => 'Initial delivery assignment.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.role', MaaccRole::Developer->value)
        ->assertJsonPath('data.active', true);

    $membership = ProjectMember::query()->where('project_id', $project->id)->where('user_id', $subject->id)->firstOrFail();

    expect($membership->granted_by_user_id)->toBe($owner->id)
        ->and($membership->expires_at?->equalTo($expiresAt))->toBeTrue()
        ->and($subject->hasMaaccPermission($team, MaaccPermission::ManageAgent, $project))->toBeTrue();

    $this->actingAs($owner)
        ->putJson(route('projects.members.update', [$team->slug, $project, $membership]), [
            'role' => MaaccRole::Viewer->value,
            'expires_at' => now()->addWeeks(2)->toIso8601String(),
            'reason' => 'Delivery completed; retain read-only access.',
        ])
        ->assertOk()
        ->assertJsonPath('data.role', MaaccRole::Viewer->value);

    $this->actingAs($owner)
        ->postJson(route('projects.members.certify', [$team->slug, $project, $membership]), [
            'note' => 'Quarterly owner review completed.',
        ])
        ->assertOk()
        ->assertJsonPath('data.certifier', $owner->name);

    $this->actingAs($owner)
        ->postJson(route('projects.members.revoke', [$team->slug, $project, $membership]), [
            'reason' => 'User moved to another project.',
        ])
        ->assertOk()
        ->assertJsonPath('data.active', false);

    $membership->refresh();

    expect($membership->revoked_by_user_id)->toBe($owner->id)
        ->and($membership->revoked_at)->not->toBeNull()
        ->and($subject->hasMaaccPermission($team, MaaccPermission::View, $project))->toBeFalse()
        ->and(AuditEvent::query()->where('auditable_type', ProjectMember::class)->pluck('action')->all())
        ->toBe([
            'project_member.assigned',
            'project_member.role_changed',
            'project_member.certified',
            'project_member.revoked',
        ]);
});

test('expired and revoked access terms fail closed while retained evidence remains listable', function () {
    [$owner, $team] = ownerAndTeam();
    $project = Project::factory()->for(Application::factory()->for($team))->create();
    $subject = teamMember($team);

    $membership = ProjectMember::query()->create([
        'project_id' => $project->id,
        'user_id' => $subject->id,
        'maacc_role' => MaaccRole::ProjectOwner,
        'granted_by_user_id' => $owner->id,
        'expires_at' => now()->subMinute(),
        'reason' => 'Expired temporary access.',
    ]);

    expect($membership->isActive())->toBeFalse()
        ->and($subject->maaccRoleFor($project))->toBeNull()
        ->and($subject->hasMaaccPermission($team, MaaccPermission::ManageProject, $project))->toBeFalse();

    $this->actingAs($owner)
        ->getJson(route('projects.members.index', [$team->slug, $project]))
        ->assertOk()
        ->assertJsonPath('data.0.id', $membership->id)
        ->assertJsonPath('data.0.active', false);
});

test('project access endpoints enforce project authority tenant ownership and governed roles', function () {
    [, $team] = ownerAndTeam();
    $project = Project::factory()->for(Application::factory()->for($team))->create();
    $developer = projectRoleUser($team, $project, MaaccRole::Developer);
    $subject = teamMember($team);

    $this->actingAs($developer)
        ->postJson(route('projects.members.store', [$team->slug, $project]), [
            'user_id' => $subject->id,
            'role' => MaaccRole::Viewer->value,
            'reason' => 'Unauthorized attempt.',
        ])
        ->assertForbidden();

    [$foreignOwner, $foreignTeam] = ownerAndTeam();
    $foreignProject = Project::factory()->for(Application::factory()->for($foreignTeam))->create();

    $this->actingAs($foreignOwner)
        ->postJson(route('projects.members.store', [$foreignTeam->slug, $foreignProject]), [
            'user_id' => $subject->id,
            'role' => MaaccRole::Viewer->value,
            'reason' => 'Cross-tenant attempt.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user_id');

    $this->actingAs($foreignOwner)
        ->postJson(route('projects.members.store', [$foreignTeam->slug, $foreignProject]), [
            'user_id' => $foreignOwner->id,
            'role' => MaaccRole::PlatformAdmin->value,
            'reason' => 'Invalid project-scoped platform role.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');
});

test('a membership route cannot be paired with a different project', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $otherProject = Project::factory()->for($application)->create();
    $subject = teamMember($team);
    $membership = ProjectMember::query()->create([
        'project_id' => $project->id,
        'user_id' => $subject->id,
        'maacc_role' => MaaccRole::Viewer,
    ]);

    $this->actingAs($owner)
        ->postJson(route('projects.members.revoke', [$team->slug, $otherProject, $membership]), [
            'reason' => 'Mismatched parent attempt.',
        ])
        ->assertForbidden();
});
