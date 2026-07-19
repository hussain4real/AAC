<?php

use App\Enums\AgentStatus;
use App\Enums\ApprovalType;
use App\Enums\MaaccRole;
use App\Models\Agent;
use App\Models\Application;
use App\Models\ApprovalRequest;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\Team;
use App\Models\ToolContract;
use App\Support\Governance\AgentReadinessGate;

/**
 * Build an application, project and approved model for the given team.
 *
 * @return array{0: Project, 1: LlmProvider}
 */
function projectWithModel(Team $team): array
{
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create(['environment' => 'production']);
    $llm = LlmProvider::factory()->for($team)->create();
    $project->llmProviders()->attach($llm);

    return [$project, $llm];
}

test('a platform admin can create a draft agent with an initial version', function () {
    [$owner, $team] = ownerAndTeam();
    [$project, $llm] = projectWithModel($team);
    $tool = ToolContract::factory()->for($team)->for($project->application)->create();

    $this->actingAs($owner)
        ->post(route('agents.store', ['current_team' => $team->slug]), [
            'project_id' => $project->id,
            'llm_provider_id' => $llm->id,
            'name' => 'Operations Summary Agent',
            'agent_slug' => 'operations-summary',
            'system_prompt' => 'You are the Operations Summary Agent.',
            'temperature' => 0.3,
            'max_tokens' => 1500,
            'sensitivity' => 'confidential',
            'requires_runtime_approval' => true,
            'tool_ids' => [$tool->id],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $agent = Agent::firstWhere('agent_slug', 'operations-summary');

    expect($agent)->not->toBeNull()
        ->and($agent->status)->toBe(AgentStatus::Draft)
        ->and($agent->current_version_id)->not->toBeNull()
        ->and($agent->sensitivity->value)->toBe('confidential')
        ->and($agent->requires_runtime_approval)->toBeTrue()
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
    $reviewer = teamAdminReviewer($team);
    [$project, $llm] = projectWithModel($team);
    $agent = Agent::factory()->for($project)->for($llm, 'llmProvider')->create(['version' => 'v1']);

    $this->actingAs($owner)
        ->post(route('agents.publish', ['current_team' => $team->slug, 'agent' => $agent->slug]))
        ->assertRedirect();

    $approval = ApprovalRequest::query()->pending()->where('type', ApprovalType::AgentPublication)->firstOrFail();
    $this->actingAs($reviewer)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $approval->id]))
        ->assertRedirect();

    $agent->refresh();

    expect($agent->status)->toBe(AgentStatus::Published)
        ->and($agent->published_at)->not->toBeNull()
        ->and($agent->version)->toBe('v2')
        ->and($agent->currentVersion->version)->toBe('v2')
        ->and($agent->currentVersion->status)->toBe(AgentStatus::Published)
        ->and($agent->currentVersion->settings['execution_snapshot']['snapshot_version'])->toBe(1)
        ->and($agent->currentVersion->settings['execution_snapshot']['runtime_policy_version'])->toBe('1.0.0')
        ->and($agent->currentVersion->settings['execution_snapshot']['agent']['prompt'])->toBe($agent->system_prompt)
        ->and($agent->currentVersion->settings['execution_snapshot']['agent']['model_id'])->toBe($agent->llm_provider_id)
        ->and($agent->currentVersion->settings['execution_snapshot']['agent']['sensitivity'])->toBe($agent->sensitivity->value)
        ->and($agent->currentVersion->settings['execution_snapshot']['agent']['requires_runtime_approval'])->toBe($agent->requires_runtime_approval)
        ->and($agent->currentVersion->settings['execution_snapshot']['application']['environment'])->toBe($agent->project->application->environment->value)
        ->and($agent->currentVersion->settings['execution_snapshot']['project']['environment'])->toBe($agent->project->environment->value)
        ->and($agent->currentVersion->settings['execution_snapshot']['tools'])->toBe([])
        ->and($agent->currentVersion->settings['configuration_hash'])
        ->toBe(app(AgentReadinessGate::class)->configurationHash($agent));
});

test('a developer can create an agent in a project they belong to', function () {
    [, $team] = ownerAndTeam();
    $developer = teamMember($team);
    [$project, $llm] = projectWithModel($team);
    $project->members()->attach($developer, ['maacc_role' => MaaccRole::Developer->value]);

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
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect(Agent::whereAgentSlug('dev-agent')->exists())->toBeTrue();
});
