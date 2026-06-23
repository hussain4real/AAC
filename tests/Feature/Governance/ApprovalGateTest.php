<?php

use App\Actions\Maac\ApproveApprovalRequest;
use App\Enums\AgentStatus;
use App\Enums\ApprovalType;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Enums\LlmStatus;
use App\Enums\McpConnectorStatus;
use App\Exceptions\ApprovalBlockedException;
use App\Models\Agent;
use App\Models\Application;
use App\Models\ApprovalRequest;
use App\Models\LlmProvider;
use App\Models\McpConnector;
use App\Models\Project;
use App\Models\Team;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use App\Support\Governance\ApprovalGate;
use App\Support\Governance\ApprovalManager;

/**
 * Build a published agent on a production model, owned by the team.
 */
function publishableAgent(Team $team): Agent
{
    $application = Application::factory()->for($team)->create(['environment' => Environment::Production]);
    $project = Project::factory()->for($application)->create();
    $model = LlmProvider::factory()->for($team)->create([
        'status' => LlmStatus::Approved,
        'environments' => [Environment::Production->value],
    ]);

    return Agent::factory()->for($project)->for($model)->create(['status' => AgentStatus::Draft]);
}

test('an agent with no unmet prerequisites is approvable', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = publishableAgent($team);
    $request = app(ApprovalManager::class)->requestAgentPublication($agent, $owner, Environment::Production);

    expect(app(ApprovalGate::class)->blockers($request))->toBe([])
        ->and(app(ApprovalGate::class)->isSatisfied($request))->toBeTrue();
});

test('an agent is blocked while a required tool is still awaiting approval', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = publishableAgent($team);
    $tool = ToolContract::factory()->for($team)->create(['requires_approval' => true, 'execution_mode' => ExecMode::Hosted]);
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    // Open a pending tool-contract approval — the agent now depends on it.
    app(ApprovalManager::class)->requestToolContractApproval($tool, $owner);
    $request = app(ApprovalManager::class)->requestAgentPublication($agent, $owner, Environment::Production);

    expect(app(ApprovalGate::class)->blockers($request))
        ->toContain("Tool {$tool->name} is still awaiting approval.");
});

test('an agent is blocked while a client-side tool is unimplemented in the environment', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = publishableAgent($team);
    $tool = ToolContract::factory()->for($team)->create(['requires_approval' => false, 'execution_mode' => ExecMode::Client]);
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    $request = app(ApprovalManager::class)->requestAgentPublication($agent, $owner, Environment::Production);

    expect(app(ApprovalGate::class)->blockers($request))
        ->toContain("Tool {$tool->name} has no implemented handler in Production.");

    // Once implemented, the blocker clears.
    ToolImplementation::create([
        'tool_contract_id' => $tool->id,
        'application_id' => $agent->project->application_id,
        'environment' => Environment::Production->value,
        'status' => ImplStatus::Implemented,
    ]);

    expect(app(ApprovalGate::class)->blockers($request->fresh()->load('subject')))->toBe([]);
});

test('an agent is blocked when its model is not approved for the environment', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $model = LlmProvider::factory()->for($team)->create([
        'status' => LlmStatus::Approved,
        'environments' => [Environment::Development->value],
        'name' => 'Dev Only Model',
    ]);
    $agent = Agent::factory()->for($project)->for($model)->create(['status' => AgentStatus::Draft]);
    $request = app(ApprovalManager::class)->requestAgentPublication($agent, $owner, Environment::Production);

    expect(app(ApprovalGate::class)->blockers($request))
        ->toContain('Model Dev Only Model is not approved for Production.');
});

test('an agent is blocked while a connector tool uses an unavailable connector', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = publishableAgent($team);
    $connector = McpConnector::factory()->for($team)->disabled()->create();
    $tool = ToolContract::factory()->for($team)->connector($connector, 'lookup')->create();
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    $request = app(ApprovalManager::class)->requestAgentPublication($agent, $owner, Environment::Production);

    expect(app(ApprovalGate::class)->blockers($request))
        ->toContain("Tool {$tool->name} uses an MCP connector that is disabled or unavailable in Production.");

    // Enabling the connector for the target environment clears the blocker.
    $connector->update([
        'status' => McpConnectorStatus::Active,
        'environments' => [Environment::Production->value],
    ]);

    expect(app(ApprovalGate::class)->blockers($request->fresh()->load('subject')))->toBe([]);
});

test('non-agent approvals have no prerequisites', function () {
    [, $team] = ownerAndTeam();
    $request = ApprovalRequest::factory()->for($team)->create(['type' => ApprovalType::ToolContract]);

    expect(app(ApprovalGate::class)->blockers($request))->toBe([]);
});

test('the approve action refuses a blocked agent publication', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = publishableAgent($team);
    $tool = ToolContract::factory()->for($team)->create(['requires_approval' => true, 'execution_mode' => ExecMode::Hosted]);
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);
    app(ApprovalManager::class)->requestToolContractApproval($tool, $owner);
    $request = app(ApprovalManager::class)->requestAgentPublication($agent, $owner, Environment::Production);

    expect(fn () => app(ApproveApprovalRequest::class)->handle($request, $owner))
        ->toThrow(ApprovalBlockedException::class);

    expect($agent->fresh()->status)->toBe(AgentStatus::Draft);
});

test('the approval queue cannot approve a blocked agent and surfaces the reason', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = publishableAgent($team);
    $tool = ToolContract::factory()->for($team)->create(['requires_approval' => false, 'execution_mode' => ExecMode::Client]);
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);
    $request = app(ApprovalManager::class)->requestAgentPublication($agent, $owner, Environment::Production);

    $this->actingAs($owner)
        ->post(route('approvals.approve', ['current_team' => $team->slug, 'approvalRequest' => $request->id]))
        ->assertRedirect();

    expect($request->fresh()->isPending())->toBeTrue()
        ->and($agent->fresh()->status)->toBe(AgentStatus::Draft);
});
