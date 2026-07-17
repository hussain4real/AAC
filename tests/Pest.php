<?php

use App\Actions\Maacc\CreateToolContract;
use App\Enums\AgentStatus;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\MaaccRole;
use App\Enums\Sensitivity;
use App\Enums\TeamRole;
use App\Enums\ToolScope;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Support\Governance\AgentReadinessGate;
use App\Support\Runtime\Contracts\LlmRouter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Runtime\FakeLlmRouter;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind different classes or traits.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

/**
 * Build a team owner (MAACC Platform Admin) and their current team.
 *
 * @return array{0: User, 1: Team}
 */
function ownerAndTeam(): array
{
    $owner = User::factory()->create();

    return [$owner, $owner->currentTeam];
}

/**
 * Add a plain team member (not a Platform Admin) to the given team and make it
 * their current team.
 */
function teamMember(Team $team): User
{
    $member = User::factory()->create();
    $team->members()->attach($member, ['role' => TeamRole::Member->value]);
    $member->switchTeam($team);

    return $member;
}

/**
 * Add a separate team administrator who may decide four-eyes approvals.
 */
function teamAdminReviewer(Team $team): User
{
    $reviewer = User::factory()->create();
    $team->members()->attach($reviewer, ['role' => TeamRole::Admin->value]);
    $reviewer->switchTeam($team);

    return $reviewer;
}

/**
 * Add a plain team member and grant them a MAACC role on the given project.
 */
function projectRoleUser(Team $team, Project $project, MaaccRole $role): User
{
    $user = teamMember($team);
    $project->members()->attach($user, ['maacc_role' => $role->value]);

    return $user;
}

/**
 * Build a published agent (with its application, project, and model) owned by
 * the given team.
 *
 * @param  array<string, mixed>  $attributes
 */
function maaccAgent(Team $team, array $attributes = []): Agent
{
    $application = Application::factory()->for($team)->create(['environment' => Environment::Production]);
    $project = Project::factory()->for($application)->create(['environment' => Environment::Production]);
    $model = LlmProvider::factory()->for($team)->create();
    $project->llmProviders()->attach($model);

    $factory = Agent::factory()->for($project)->for($model);
    $status = $attributes['status'] ?? null;

    if ($status === AgentStatus::Published || $status === AgentStatus::Published->value) {
        $factory = $factory->published();
        unset($attributes['status']);
    }

    return $factory->create($attributes);
}

/**
 * Build an agent run wired to the agent's application/project/model.
 *
 * @param  array<string, mixed>  $attributes
 */
function maaccRun(Agent $agent, array $attributes = []): AgentRun
{
    return AgentRun::factory()->create(array_merge([
        'agent_id' => $agent->id,
        'project_id' => $agent->project_id,
        'application_id' => $agent->project->application_id,
        'llm_provider_id' => $agent->llm_provider_id,
    ], $attributes));
}

/**
 * Snapshot a test agent's current execution configuration as its approved version.
 */
function approveCurrentAgentConfiguration(Agent $agent): Agent
{
    $agent->loadMissing('currentVersion');

    if ($agent->currentVersion === null) {
        throw new RuntimeException('The test agent has no published version to approve.');
    }

    $settings = $agent->currentVersion->settings ?? [];
    $settings['configuration_hash'] = app(AgentReadinessGate::class)->configurationHash($agent);
    $agent->currentVersion->update(['settings' => $settings]);

    return $agent->refresh();
}

/**
 * Bind a deterministic, scripted {@see FakeLlmRouter} for the current test so a
 * run completes without any live provider call.
 */
function bindFakeRouter(): FakeLlmRouter
{
    $fake = new FakeLlmRouter;
    app()->instance(LlmRouter::class, $fake);

    return $fake;
}

/**
 * Minimal valid create payload for a client-side tool contract, suitable for
 * {@see CreateToolContract::handle()}.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function toolContractData(array $overrides = []): array
{
    return array_merge([
        'name' => 'Fetch Records',
        'scope' => ToolScope::Project->value,
        'execution_mode' => ExecMode::Client->value,
        'sensitivity' => Sensitivity::Internal->value,
        'requires_approval' => false,
        'timeout_seconds' => 15,
        'max_payload_kb' => 256,
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['result' => 'string'],
    ], $overrides);
}
