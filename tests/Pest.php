<?php

use App\Enums\MaacRole;
use App\Enums\TeamRole;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
 * Build a team owner (MAAC Platform Admin) and their current team.
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
 * Add a plain team member and grant them a MAAC role on the given project.
 */
function projectRoleUser(Team $team, Project $project, MaacRole $role): User
{
    $user = teamMember($team);
    $project->members()->attach($user, ['maac_role' => $role->value]);

    return $user;
}

/**
 * Build a published agent (with its application, project, and model) owned by
 * the given team.
 *
 * @param  array<string, mixed>  $attributes
 */
function maacAgent(Team $team, array $attributes = []): Agent
{
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $model = LlmProvider::factory()->for($team)->create();

    return Agent::factory()->for($project)->for($model)->create($attributes);
}

/**
 * Build an agent run wired to the agent's application/project/model.
 *
 * @param  array<string, mixed>  $attributes
 */
function maacRun(Agent $agent, array $attributes = []): AgentRun
{
    return AgentRun::factory()->create(array_merge([
        'agent_id' => $agent->id,
        'project_id' => $agent->project_id,
        'application_id' => $agent->project->application_id,
        'llm_provider_id' => $agent->llm_provider_id,
    ], $attributes));
}
