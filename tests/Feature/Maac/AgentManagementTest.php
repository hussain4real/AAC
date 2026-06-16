<?php

use App\Enums\AgentStatus;
use App\Enums\MaacRole;
use App\Models\Agent;
use App\Models\Application;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\Team;
use App\Models\ToolContract;

/**
 * Build an application, project and approved model for the given team.
 *
 * @return array{0: Project, 1: LlmProvider}
 */
function projectWithModel(Team $team): array
{
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $llm = LlmProvider::factory()->for($team)->create();

    return [$project, $llm];
}

test('a platform admin can create a draft agent with an initial version', function () {
    [$owner, $team] = ownerAndTeam();
    [$project, $llm] = projectWithModel($team);
    $tool = ToolContract::factory()->for($team)->create();

    $this->actingAs($owner)
        ->post(route('agents.store', ['current_team' => $team->slug]), [
            'project_id' => $project->id,
            'llm_provider_id' => $llm->id,
            'name' => 'Operations Summary Agent',
            'agent_slug' => 'operations-summary',
            'system_prompt' => 'You are the Operations Summary Agent.',
            'temperature' => 0.3,
            'max_tokens' => 1500,
            'tool_ids' => [$tool->id],
        ])
        ->assertRedirect();

    $agent = Agent::firstWhere('agent_slug', 'operations-summary');

    expect($agent)->not->toBeNull()
        ->and($agent->status)->toBe(AgentStatus::Draft)
        ->and($agent->current_version_id)->not->toBeNull()
        ->and($agent->versions()->count())->toBe(1)
        ->and($agent->tools()->pluck('tool_contracts.id')->all())->toBe([$tool->id]);
});

test('agent creation validates required fields', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('agents.store', ['current_team' => $team->slug]), [])
        ->assertSessionHasErrors(['project_id', 'llm_provider_id', 'name', 'agent_slug', 'system_prompt', 'temperature', 'max_tokens']);
});

test('a non-admin without a project role cannot create an agent', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);
    [$project, $llm] = projectWithModel($team);

    $this->actingAs($member)
        ->post(route('agents.store', ['current_team' => $team->slug]), [
            'project_id' => $project->id,
            'llm_provider_id' => $llm->id,
            'name' => 'Blocked Agent',
            'agent_slug' => 'blocked-agent',
            'system_prompt' => 'No.',
            'temperature' => 0.2,
            'max_tokens' => 1000,
        ])
        ->assertForbidden();
});

test('publishing an agent snapshots a new version and bumps the version label', function () {
    [$owner, $team] = ownerAndTeam();
    [$project, $llm] = projectWithModel($team);
    $agent = Agent::factory()->for($project)->for($llm, 'llmProvider')->create(['version' => 'v1']);

    $this->actingAs($owner)
        ->post(route('agents.publish', ['current_team' => $team->slug, 'agent' => $agent->slug]))
        ->assertRedirect();

    $agent->refresh();

    expect($agent->status)->toBe(AgentStatus::Published)
        ->and($agent->published_at)->not->toBeNull()
        ->and($agent->version)->toBe('v2')
        ->and($agent->currentVersion->version)->toBe('v2')
        ->and($agent->currentVersion->status)->toBe(AgentStatus::Published);
});

test('a developer can create an agent in a project they belong to', function () {
    [, $team] = ownerAndTeam();
    $developer = teamMember($team);
    [$project, $llm] = projectWithModel($team);
    $project->members()->attach($developer, ['maac_role' => MaacRole::Developer->value]);

    $this->actingAs($developer)
        ->post(route('agents.store', ['current_team' => $team->slug]), [
            'project_id' => $project->id,
            'llm_provider_id' => $llm->id,
            'name' => 'Dev Agent',
            'agent_slug' => 'dev-agent',
            'system_prompt' => 'Hello.',
            'temperature' => 0.4,
            'max_tokens' => 1200,
        ])
        ->assertRedirect();

    expect(Agent::whereAgentSlug('dev-agent')->exists())->toBeTrue();
});
