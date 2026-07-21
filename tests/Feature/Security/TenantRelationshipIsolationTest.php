<?php

use App\Actions\Maacc\CreateAgent;
use App\Actions\Maacc\CreateProject;
use App\Actions\Maacc\CreateToolContract;
use App\Actions\Maacc\SyncAgentTools;
use App\Actions\Maacc\UpdateAgent;
use App\Actions\Maacc\UpdateProject;
use App\Enums\AgentStatus;
use App\Enums\MaaccPermission;
use App\Enums\TeamRole;
use App\Models\Agent;
use App\Models\Application;
use App\Models\ApprovalRequest;
use App\Models\LlmProvider;
use App\Models\ModelRoutingPolicy;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\QuotaLimit;
use App\Models\Team;
use App\Models\ToolContract;
use App\Models\User;
use App\Support\GovernanceConsoleData;
use Illuminate\Database\QueryException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

test('project creation rejects an application owned by another tenant', function () {
    [$owner, $team] = ownerAndTeam();
    $foreignApplication = Application::factory()->for(User::factory()->create()->currentTeam)->create();

    $this->actingAs($owner)
        ->post(route('projects.store', ['current_team' => $team->slug]), [
            'application_id' => $foreignApplication->id,
            'name' => 'Injected Project',
            'environment' => 'production',
        ])
        ->assertSessionHasErrors('application_id');

    expect(Project::where('name', 'Injected Project')->exists())->toBeFalse();
});

test('project creation rejects an approved model owned by another tenant', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $foreignProvider = LlmProvider::factory()->for(User::factory()->create()->currentTeam)->create();

    $this->actingAs($owner)
        ->post(route('projects.store', ['current_team' => $team->slug]), [
            'application_id' => $application->id,
            'name' => 'Foreign Model Project',
            'environment' => 'production',
            'llm_provider_ids' => [$foreignProvider->id],
        ])
        ->assertSessionHasErrors('llm_provider_ids.0');

    expect(Project::where('name', 'Foreign Model Project')->exists())->toBeFalse();
});

test('agent creation rejects a model that is not approved for its project', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create(['environment' => 'production']);
    $unapprovedProvider = LlmProvider::factory()->for($team)->create();

    $this->actingAs($owner)
        ->post(route('agents.store', ['current_team' => $team->slug]), agentPayload($project, $unapprovedProvider))
        ->assertSessionHasErrors('llm_provider_id');

    expect(Agent::where('agent_slug', 'tenant-isolation-agent')->exists())->toBeFalse();
});

test('agent creation rejects a tool owned by another tenant', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create(['environment' => 'production']);
    $provider = LlmProvider::factory()->for($team)->create();
    $project->llmProviders()->attach($provider);

    $foreignTeam = User::factory()->create()->currentTeam;
    $foreignApplication = Application::factory()->for($foreignTeam)->create();
    $foreignTool = ToolContract::factory()->for($foreignTeam)->for($foreignApplication)->create();

    $this->actingAs($owner)
        ->post(route('agents.store', ['current_team' => $team->slug]), agentPayload($project, $provider, [
            'tool_ids' => [$foreignTool->id],
        ]))
        ->assertSessionHasErrors('tool_ids.0');

    expect(Agent::where('agent_slug', 'tenant-isolation-agent')->exists())->toBeFalse();
});

test('tool creation rejects an application owned by another tenant', function () {
    [$owner, $team] = ownerAndTeam();
    $foreignApplication = Application::factory()->for(User::factory()->create()->currentTeam)->create();

    $this->actingAs($owner)
        ->post(route('tools.store', ['current_team' => $team->slug]), toolContractData([
            'application_id' => $foreignApplication->id,
        ]))
        ->assertSessionHasErrors('application_id');

    expect(ToolContract::where('name', 'Fetch Records')->exists())->toBeFalse();
});

test('ordinary agent writes cannot publish an agent', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create(['environment' => 'production']);
    $provider = LlmProvider::factory()->for($team)->create();
    $project->llmProviders()->attach($provider);

    $this->actingAs($owner)
        ->post(route('agents.store', ['current_team' => $team->slug]), agentPayload($project, $provider, [
            'status' => AgentStatus::Published->value,
        ]))
        ->assertSessionHasErrors('status');

    $agent = Agent::factory()->for($project)->for($provider, 'llmProvider')->create();

    $this->actingAs($owner)
        ->put(route('agents.update', ['current_team' => $team->slug, 'agent' => $agent->slug]), [
            'status' => AgentStatus::Published->value,
        ])
        ->assertSessionHasErrors('status');

    expect($agent->fresh()->status)->toBe(AgentStatus::Draft);
});

test('direct project actions reject a foreign application before persistence', function () {
    [, $team] = ownerAndTeam();
    $foreignApplication = Application::factory()->for(User::factory()->create()->currentTeam)->create();

    expect(fn () => app(CreateProject::class)->handle($team, [
        'application_id' => $foreignApplication->id,
        'name' => 'Direct Foreign Project',
        'environment' => 'production',
    ]))->toThrow(ValidationException::class);

    expect(Project::where('name', 'Direct Foreign Project')->exists())->toBeFalse();
});

test('direct agent actions reject unapproved models and foreign tools', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create(['environment' => 'production']);
    $provider = LlmProvider::factory()->for($team)->create();
    $foreignTeam = User::factory()->create()->currentTeam;
    $foreignApplication = Application::factory()->for($foreignTeam)->create();
    $foreignTool = ToolContract::factory()->for($foreignTeam)->for($foreignApplication)->create();

    expect(fn () => app(CreateAgent::class)->handle($project, agentPayload($project, $provider)))
        ->toThrow(ValidationException::class);

    $project->llmProviders()->attach($provider);
    $agent = Agent::factory()->for($project)->for($provider, 'llmProvider')->create();

    expect(fn () => app(SyncAgentTools::class)->handle($agent, [$foreignTool->id]))
        ->toThrow(ValidationException::class);

    expect($agent->tools()->exists())->toBeFalse();
});

test('direct agent updates cannot reparent or publish an agent', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create(['environment' => 'production']);
    $provider = LlmProvider::factory()->for($team)->create();
    $project->llmProviders()->attach($provider);
    $agent = Agent::factory()->for($project)->for($provider, 'llmProvider')->create();
    $foreignProject = Project::factory()->create();

    app(UpdateAgent::class)->handle($agent, [
        'name' => 'Safe Direct Update',
        'project_id' => $foreignProject->id,
        'status' => AgentStatus::Published->value,
    ]);

    $agent->refresh();

    expect($agent->name)->toBe('Safe Direct Update')
        ->and($agent->project_id)->toBe($project->id)
        ->and($agent->status)->toBe(AgentStatus::Draft);
});

test('project model assignments are protected by application and database invariants', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $project = $agent->project;
    $unapprovedProvider = LlmProvider::factory()->for($team)->create();

    expect(fn () => app(UpdateProject::class)->handle($project, [
        'llm_provider_ids' => [],
    ]))->toThrow(ValidationException::class, 'cannot be removed');

    expect(fn () => $agent->forceFill([
        'llm_provider_id' => $unapprovedProvider->id,
    ])->save())->toThrow(QueryException::class);

    expect($agent->fresh()->llm_provider_id)->not->toBe($unapprovedProvider->id)
        ->and($project->llmProviders()->whereKey($agent->fresh()->llm_provider_id)->exists())->toBeTrue();
});

test('the provider invariant migration repairs legacy agent links before adding its constraint', function () {
    $defaultConnection = DB::getDefaultConnection();
    $connection = 'legacy_provider_invariant';
    $projectId = (string) Str::uuid();
    $providerId = (string) Str::uuid();
    $agentId = (string) Str::uuid();
    $applicationId = (string) Str::uuid();
    $migration = require database_path('migrations/2026_07_15_103958_enforce_agent_project_provider_invariant.php');

    config(["database.connections.{$connection}" => [
        ...config('database.connections.sqlite'),
        'database' => ':memory:',
        'foreign_key_constraints' => true,
    ]]);
    DB::purge($connection);
    DB::setDefaultConnection($connection);

    try {
        Schema::create('applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('team_id');
        });
        Schema::create('projects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('application_id');
        });
        Schema::create('llm_providers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('team_id');
        });
        Schema::create('project_llm_provider', function (Blueprint $table): void {
            $table->id();
            $table->uuid('project_id');
            $table->uuid('llm_provider_id');
            $table->timestamps();
            $table->unique(['project_id', 'llm_provider_id']);
        });
        Schema::create('agents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('llm_provider_id');
        });
        DB::table('applications')->insert(['id' => $applicationId, 'team_id' => 1]);
        DB::table('projects')->insert(['id' => $projectId, 'application_id' => $applicationId]);
        DB::table('llm_providers')->insert(['id' => $providerId, 'team_id' => 1]);
        DB::table('agents')->insert([
            'id' => $agentId,
            'project_id' => $projectId,
            'llm_provider_id' => $providerId,
        ]);

        expect(DB::table('project_llm_provider')->count())->toBe(0);

        $migration->up();

        expect(DB::table('project_llm_provider')->where([
            'project_id' => $projectId,
            'llm_provider_id' => $providerId,
        ])->exists())->toBeTrue();
    } finally {
        DB::setDefaultConnection($defaultConnection);
        DB::purge($connection);
    }
});

test('the provider invariant migration refuses to legitimize a cross-tenant legacy agent', function () {
    $defaultConnection = DB::getDefaultConnection();
    $connection = 'cross_tenant_provider_invariant';
    $applicationId = (string) Str::uuid();
    $projectId = (string) Str::uuid();
    $providerId = (string) Str::uuid();
    $migration = require database_path('migrations/2026_07_15_103958_enforce_agent_project_provider_invariant.php');

    config(["database.connections.{$connection}" => [
        ...config('database.connections.sqlite'),
        'database' => ':memory:',
        'foreign_key_constraints' => true,
    ]]);
    DB::purge($connection);
    DB::setDefaultConnection($connection);

    try {
        Schema::create('applications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('team_id');
        });
        Schema::create('projects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('application_id');
        });
        Schema::create('llm_providers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('team_id');
        });
        Schema::create('project_llm_provider', function (Blueprint $table): void {
            $table->id();
            $table->uuid('project_id');
            $table->uuid('llm_provider_id');
            $table->timestamps();
            $table->unique(['project_id', 'llm_provider_id']);
        });
        Schema::create('agents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->uuid('llm_provider_id');
        });
        DB::table('applications')->insert(['id' => $applicationId, 'team_id' => 1]);
        DB::table('projects')->insert(['id' => $projectId, 'application_id' => $applicationId]);
        DB::table('llm_providers')->insert(['id' => $providerId, 'team_id' => 2]);
        DB::table('agents')->insert([
            'id' => (string) Str::uuid(),
            'project_id' => $projectId,
            'llm_provider_id' => $providerId,
        ]);

        expect(fn () => $migration->up())
            ->toThrow(RuntimeException::class, '1 legacy agent(s) reference a provider outside');
        expect(DB::table('project_llm_provider')->count())->toBe(0);
    } finally {
        DB::setDefaultConnection($defaultConnection);
        DB::purge($connection);
    }
});

test('direct tool actions reject a foreign application before persistence', function () {
    [, $team] = ownerAndTeam();
    $foreignApplication = Application::factory()->for(User::factory()->create()->currentTeam)->create();

    expect(fn () => app(CreateToolContract::class)->handle($team, toolContractData([
        'application_id' => $foreignApplication->id,
    ])))->toThrow(ValidationException::class);

    expect(ToolContract::where('name', 'Fetch Records')->exists())->toBeFalse();
});

test('direct agent actions preserve legitimate same-tenant relationships', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create(['environment' => 'production']);
    $provider = LlmProvider::factory()->for($team)->create();
    $project->llmProviders()->attach($provider);
    $tool = ToolContract::factory()->for($team)->for($application)->create();

    $agent = app(CreateAgent::class)->handle($project, agentPayload($project, $provider, [
        'agent_slug' => 'legitimate-direct-agent',
        'tool_ids' => [$tool->id],
    ]));

    expect($agent->status)->toBe(AgentStatus::Draft)
        ->and($agent->project_id)->toBe($project->id)
        ->and($agent->llm_provider_id)->toBe($provider->id)
        ->and($agent->tools()->pluck('tool_contracts.id')->all())->toBe([$tool->id]);
});

test('legacy unknown and null project roles fail closed without crashing governance', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();

    DB::table('project_members')->insert([
        'project_id' => $project->id,
        'user_id' => $member->id,
        'maacc_role' => 'legacy_unknown_role',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect($member->fresh()->maaccRoleFor($project))->toBeNull()
        ->and($member->fresh()->hasMaaccPermissionOnAnyProject($team, MaaccPermission::ManageAgent))->toBeFalse()
        ->and((new ProjectMember(['maacc_role' => null]))->maacc_role)->toBeNull()
        ->and(GovernanceConsoleData::forTeam($team)['roles'])->not->toBeEmpty();
});

test('route-bound resources cannot cross the selected team context', function () {
    [$owner, $selectedTeam] = ownerAndTeam();
    $foreignTeam = Team::factory()->create();
    $foreignTeam->members()->attach($owner, ['role' => TeamRole::Owner->value]);
    $foreignApplication = Application::factory()->for($foreignTeam)->create();
    $foreignProject = Project::factory()->for($foreignApplication)->create();
    $foreignProvider = LlmProvider::factory()->for($foreignTeam)->create();
    $foreignProject->llmProviders()->attach($foreignProvider);
    $foreignAgent = Agent::factory()->for($foreignProject)->for($foreignProvider, 'llmProvider')->create();
    $foreignTool = ToolContract::factory()->for($foreignTeam)->for($foreignApplication)->create();
    $foreignQuota = QuotaLimit::factory()->for($foreignTeam)->create();
    $foreignRoutingPolicy = ModelRoutingPolicy::factory()->for($foreignTeam)->for($foreignAgent)->create();
    $foreignApproval = ApprovalRequest::factory()->for($foreignTeam)->create();

    $this->actingAs($owner)
        ->put(route('projects.update', ['current_team' => $selectedTeam->slug, 'project' => $foreignProject->slug]), ['name' => 'Cross-team project'])
        ->assertNotFound();

    $this->actingAs($owner)
        ->put(route('agents.update', ['current_team' => $selectedTeam->slug, 'agent' => $foreignAgent->slug]), ['name' => 'Cross-team agent'])
        ->assertNotFound();

    $this->actingAs($owner)
        ->put(route('tools.update', ['current_team' => $selectedTeam->slug, 'tool' => $foreignTool->slug]), ['description' => 'Cross-team tool'])
        ->assertNotFound();

    $this->actingAs($owner)
        ->put(route('quotas.update', ['current_team' => $selectedTeam->slug, 'quotaLimit' => $foreignQuota->id]), ['enabled' => false])
        ->assertNotFound();

    $this->actingAs($owner)
        ->put(route('routing-policies.update', ['current_team' => $selectedTeam->slug, 'modelRoutingPolicy' => $foreignRoutingPolicy->id]), ['name' => 'Cross-team routing'])
        ->assertNotFound();

    $this->actingAs($owner)
        ->post(route('approvals.approve', ['current_team' => $selectedTeam->slug, 'approvalRequest' => $foreignApproval->id]), [])
        ->assertNotFound();

    expect($foreignProject->fresh()->name)->not->toBe('Cross-team project')
        ->and($foreignAgent->fresh()->name)->not->toBe('Cross-team agent')
        ->and($foreignTool->fresh()->description)->not->toBe('Cross-team tool')
        ->and($foreignQuota->fresh()->enabled)->toBeTrue()
        ->and($foreignRoutingPolicy->fresh()->name)->not->toBe('Cross-team routing')
        ->and($foreignApproval->fresh()->isPending())->toBeTrue();
});

/**
 * Build a valid agent create payload for a specific project and approved model.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function agentPayload(Project $project, LlmProvider $provider, array $overrides = []): array
{
    return array_merge([
        'project_id' => $project->id,
        'llm_provider_id' => $provider->id,
        'name' => 'Tenant Isolation Agent',
        'agent_slug' => 'tenant-isolation-agent',
        'system_prompt' => 'Operate only within the authorized tenant.',
        'temperature' => 0.2,
        'max_tokens' => 1000,
    ], $overrides);
}
