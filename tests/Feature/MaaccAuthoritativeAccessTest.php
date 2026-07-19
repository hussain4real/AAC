<?php

use App\Enums\MaaccRole;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use App\Support\MaaccAccess;
use App\Support\MaaccConsoleData;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Cache::clear());

test('platform administrators receive every platform navigation entry', function () {
    [$owner, $team] = ownerAndTeam();

    expect(app(MaaccAccess::class)->forUser($owner, $team)['navigation'])
        ->toContain('identity', 'accessControl');
});

test('server issued capabilities derive from active memberships and expire fail closed', function () {
    [, $team] = ownerAndTeam();
    $project = Project::factory()->for(Application::factory()->for($team))->create();
    $user = projectRoleUser($team, $project, MaaccRole::Developer);
    $access = app(MaaccAccess::class)->forUser($user, $team);

    expect($access['roles'])->toBe([MaaccRole::Developer->value])
        ->and($access['permissions'])->toContain('agent:manage', 'tool:manage', 'view')
        ->and($access['navigation'])->toContain('agents', 'tools', 'playground')
        ->not->toContain('llm', 'vault', 'identity')
        ->and($access['projectIds'])->toBe([$project->id]);

    ProjectMember::query()->where('project_id', $project->id)->where('user_id', $user->id)->update([
        'expires_at' => now()->subSecond(),
    ]);

    $expired = app(MaaccAccess::class)->forUser($user, $team);

    expect($expired['roles'])->toBe([])
        ->and($expired['permissions'])->toBe([])
        ->and($expired['navigation'])->toBe([])
        ->and($expired['projectIds'])->toBe([]);
});

test('shared console props contain only active project records for a project actor', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $allowedProject = Project::factory()->for($application)->create();
    $hiddenProject = Project::factory()->for($application)->create();
    $provider = LlmProvider::factory()->for($team)->create();
    $allowedProject->llmProviders()->attach($provider);
    $hiddenProject->llmProviders()->attach($provider);
    $allowedAgent = Agent::factory()->for($allowedProject)->for($provider)->create();
    $hiddenAgent = Agent::factory()->for($hiddenProject)->for($provider)->create();
    AgentRun::factory()->for($application)->for($allowedProject)->for($allowedAgent)->create();
    AgentRun::factory()->for($application)->for($hiddenProject)->for($hiddenAgent)->create();
    $user = projectRoleUser($team, $allowedProject, MaaccRole::Viewer);

    $data = MaaccConsoleData::forUser($user, $team);

    expect(collect($data['projects'])->pluck('uuid')->all())->toBe([$allowedProject->id])
        ->and(collect($data['agents'])->pluck('uuid')->all())->toBe([$allowedAgent->id])
        ->and(collect($data['runs'])->pluck('projectId')->unique()->all())->toBe([$allowedProject->slug])
        ->and($data['auditEvents'])->toBe([])
        ->and($data['vaultSecrets'])->toBe([])
        ->and($data['ssoConnections'])->toBe([]);

    $this->actingAs($user)
        ->get(route('dashboard', $team->slug))
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.maacc.roles', [MaaccRole::Viewer->value])
            ->where('auth.maacc.navigation', fn ($navigation): bool => $navigation->contains('projects') && ! $navigation->contains('vault'))
            ->has('maacc.projects', 1)
            ->where('maacc.projects.0.uuid', $allowedProject->id)
            ->has('maacc.agents', 1));

    $this->actingAs($user)
        ->get(route('agents.show', [$team->slug, $allowedAgent->slug]))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('agents.show', [$team->slug, $hiddenAgent->slug]))
        ->assertForbidden();
});

test('a plain team member receives no tenant object corpus or navigation capabilities', function () {
    [, $team] = ownerAndTeam();
    Project::factory()->for(Application::factory()->for($team))->create();
    $user = teamMember($team);

    $data = MaaccConsoleData::forUser($user, $team);
    $access = app(MaaccAccess::class)->forUser($user, $team);

    expect($access['navigation'])->toBe([])
        ->and($data['apps'])->toBe([])
        ->and($data['projects'])->toBe([])
        ->and($data['agents'])->toBe([])
        ->and($data['runs'])->toBe([]);

    $this->actingAs($user)
        ->get(route('projects', $team->slug))
        ->assertForbidden();
});

test('auditor and security reviewer navigation comes from their authoritative roles', function () {
    [, $team] = ownerAndTeam();
    $project = Project::factory()->for(Application::factory()->for($team))->create();
    $auditor = projectRoleUser($team, $project, MaaccRole::Auditor);
    $reviewer = projectRoleUser($team, $project, MaaccRole::SecurityReviewer);

    expect(app(MaaccAccess::class)->forUser($auditor, $team)['navigation'])
        ->toContain('sdk', 'governance')
        ->not->toContain('connectors')
        ->and(app(MaaccAccess::class)->forUser($reviewer, $team)['navigation'])
        ->toContain('connectors', 'dataSources', 'governance')
        ->not->toContain('sdk');
});

test('project owners receive assigned global tools and the team member directory', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $provider = LlmProvider::factory()->for($team)->create();
    $agent = Agent::factory()->for($project)->for($provider)->create();
    $tool = ToolContract::factory()->for($team)->global()->create();
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);
    $owner = projectRoleUser($team, $project, MaaccRole::ProjectOwner);

    $data = MaaccConsoleData::forUser($owner, $team);

    expect(collect($data['tools'])->pluck('uuid'))->toContain($tool->id)
        ->and(collect($data['memberDirectory'])->pluck('id'))->toContain($owner->id);

    $rows = new ReflectionMethod(MaaccConsoleData::class, 'rows');
    expect($rows->invoke(null, null)->all())->toBe([])
        ->and($rows->invoke(null, [1, ['valid' => true]])->all())->toBe([['valid' => true]]);
});

test('non-admin application and tool visibility follows project membership', function () {
    [, $team] = ownerAndTeam();
    $allowedApplication = Application::factory()->for($team)->create();
    $hiddenApplication = Application::factory()->for($team)->create();
    $project = Project::factory()->for($allowedApplication)->create();
    Project::factory()->for($hiddenApplication)->create();
    $user = projectRoleUser($team, $project, MaaccRole::Viewer);
    $applicationTool = ToolContract::factory()->for($team)->for($allowedApplication)->create();
    $globalTool = ToolContract::factory()->for($team)->global()->create();
    $provider = LlmProvider::factory()->for($team)->create();
    $agent = Agent::factory()->for($project)->for($provider)->create();
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $globalTool->id]);

    expect($user->can('view', $allowedApplication))->toBeTrue()
        ->and($user->can('view', $hiddenApplication))->toBeFalse()
        ->and($user->can('view', $applicationTool))->toBeTrue()
        ->and($user->can('view', $globalTool))->toBeTrue();
});

test('project member assignment supports the browser redirect response', function () {
    [$owner, $team] = ownerAndTeam();
    $project = Project::factory()->for(Application::factory()->for($team))->create();
    $member = teamMember($team);

    $this->actingAs($owner)->post(route('projects.members.store', [
        'current_team' => $team->slug,
        'project' => $project->slug,
    ]), [
        'user_id' => $member->id,
        'role' => MaaccRole::Viewer->value,
        'reason' => 'Project onboarding',
    ])->assertRedirect();

    expect($project->projectMembers()->where('user_id', $member->id)->exists())->toBeTrue();
});
