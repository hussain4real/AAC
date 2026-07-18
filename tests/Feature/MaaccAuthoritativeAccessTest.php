<?php

use App\Enums\MaaccRole;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Support\MaaccAccess;
use App\Support\MaaccConsoleData;
use Illuminate\Support\Facades\Cache;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(fn () => Cache::clear());

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
