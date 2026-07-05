<?php

use App\Enums\AgentStatus;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\LlmStatus;
use App\Enums\RunStatus;
use App\Enums\Sensitivity;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\LlmProvider;
use App\Models\ModelRoutingPolicy;
use App\Models\Project;
use App\Models\Team;
use App\Models\ToolAssignment;
use App\Models\ToolContract;

/**
 * Build a published agent on an approved OpenAI-style model, with its
 * application/project/model all aligned to one environment so the runtime can
 * select the model.
 *
 * @param  array<string, mixed>  $agentAttributes
 */
function playgroundAgent(Team $team, array $agentAttributes = [], Environment $environment = Environment::Production): Agent
{
    $application = Application::factory()->for($team)->create(['environment' => $environment]);
    $project = Project::factory()->for($application)->create(['environment' => $environment]);
    $provider = LlmProvider::factory()->for($team)->create([
        'provider' => 'OpenAI',
        'code' => 'gpt-5.4',
        'status' => LlmStatus::Approved,
        'environments' => [$environment->value],
        'input_cost' => 1.0,
        'output_cost' => 2.0,
    ]);

    return Agent::factory()->for($project)->for($provider)->published()->create(array_merge([
        'agent_slug' => 'ops-summary',
        'system_prompt' => 'You summarize operations.',
    ], $agentAttributes));
}

/**
 * Build the default console run payload.
 *
 * @param  array<string, mixed>  $attributes
 * @return array<string, mixed>
 */
function playgroundRunPayload(array $attributes = []): array
{
    return array_merge([
        'environment' => Environment::Production->value,
        'input' => 'Summarize today.',
    ], $attributes);
}

/**
 * Assign a client-side tool contract to the given agent.
 */
function assignPlaygroundClientTool(Agent $agent, Team $team): ToolContract
{
    $tool = ToolContract::factory()
        ->for($team)
        ->for($agent->project->application)
        ->create([
            'slug' => 'lookup-records',
            'execution_mode' => ExecMode::Client,
            'input_schema' => ['query' => 'string'],
            'output_schema' => ['total' => 'number'],
        ]);

    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    return $tool;
}

/**
 * Assign the MAACC-hosted `sum` tool contract to the given agent.
 */
function assignPlaygroundHostedTool(Agent $agent, Team $team): ToolContract
{
    $tool = ToolContract::factory()
        ->for($team)
        ->for($agent->project->application)
        ->create([
            'slug' => 'sum',
            'execution_mode' => ExecMode::Hosted,
            'input_schema' => ['numbers' => 'array'],
            'output_schema' => ['total' => 'number'],
        ]);

    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    return $tool;
}

test('a team member runs a published agent from the console and gets a real completion shape', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = playgroundAgent($team);

    bindFakeRouter()->textThen('All vessels are on schedule.', tokensIn: 210, tokensOut: 70);

    $response = $this->actingAs($owner)
        ->postJson(route('playground.runs.store', ['current_team' => $team->slug, 'agent' => $agent->slug]), playgroundRunPayload())
        ->assertCreated();

    $response->assertJsonPath('status', RunStatus::Completed->value)
        ->assertJsonPath('response', 'All vessels are on schedule.')
        ->assertJsonPath('agent_slug', 'ops-summary')
        ->assertJsonPath('usage.tokens_in', 210)
        ->assertJsonPath('model', $agent->llmProvider->name)
        ->assertJsonStructure(['run_id', 'status', 'response', 'cost', 'latency_ms', 'usage' => ['tokens_in', 'tokens_out'], 'trace' => [['type', 'label', 'message']]]);

    expect($response->json('run_id'))->toStartWith('run_')
        ->and($response->json('trace'))->not->toBeEmpty()
        ->and(collect($response->json('trace'))->pluck('type'))->toContain('completed');
});

test('the console run records the console user as the caller by default', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = playgroundAgent($team);
    bindFakeRouter()->textThen('Done.');

    $response = $this->actingAs($owner)
        ->postJson(route('playground.runs.store', ['current_team' => $team->slug, 'agent' => $agent->slug]), playgroundRunPayload([
            'input' => 'Hi',
        ]))->assertCreated();

    $run = AgentRun::firstWhere('slug', $response->json('run_id'));

    expect($run->caller)->toBe('console:'.$owner->email);
});

test('the console run honors an explicit caller label', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = playgroundAgent($team);
    bindFakeRouter()->textThen('Done.');

    $response = $this->actingAs($owner)
        ->postJson(route('playground.runs.store', ['current_team' => $team->slug, 'agent' => $agent->slug]), playgroundRunPayload([
            'input' => 'Hi',
            'caller' => 'qa-suite',
        ]))->assertCreated();

    expect(AgentRun::firstWhere('slug', $response->json('run_id'))->caller)->toBe('qa-suite');
});

test('a draft agent cannot be run from the console', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = playgroundAgent($team, ['status' => AgentStatus::Draft]);
    bindFakeRouter()->textThen('Should not run.');

    $this->actingAs($owner)
        ->postJson(route('playground.runs.store', ['current_team' => $team->slug, 'agent' => $agent->slug]), playgroundRunPayload([
            'input' => 'Run me',
        ]))
        ->assertStatus(422)
        ->assertJsonPath('message', 'The agent must be published before it can be run from the console.');
});

test('the run request validates the input prompt', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = playgroundAgent($team);

    $this->actingAs($owner)
        ->postJson(route('playground.runs.store', ['current_team' => $team->slug, 'agent' => $agent->slug]), playgroundRunPayload([
            'input' => '',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['input']);
});

test('the run request validates the selected playground environment', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = playgroundAgent($team);

    $this->actingAs($owner)
        ->postJson(route('playground.runs.store', ['current_team' => $team->slug, 'agent' => $agent->slug]), [
            'environment' => 'qa',
            'input' => 'Hi',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['environment']);
});

test('a console run executes in the selected matching environment', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = playgroundAgent($team, [], Environment::Development);
    bindFakeRouter()->textThen('Development run complete.');

    $response = $this->actingAs($owner)
        ->postJson(route('playground.runs.store', ['current_team' => $team->slug, 'agent' => $agent->slug]), playgroundRunPayload([
            'environment' => Environment::Development->value,
            'input' => 'Run in development',
        ]))
        ->assertCreated()
        ->assertJsonPath('status', RunStatus::Completed->value);

    $run = AgentRun::firstWhere('slug', $response->json('run_id'));

    expect($run->environment)->toBe(Environment::Development);
});

test('a console run may use a project environment that differs from the application environment', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = playgroundAgent($team, [], Environment::Staging);
    $agent->project->application->update(['environment' => Environment::Production]);
    bindFakeRouter()->textThen('Staging project run complete.');

    $response = $this->actingAs($owner)
        ->postJson(route('playground.runs.store', ['current_team' => $team->slug, 'agent' => $agent->slug]), playgroundRunPayload([
            'environment' => Environment::Staging->value,
            'input' => 'Run the staging project',
        ]))
        ->assertCreated()
        ->assertJsonPath('status', RunStatus::Completed->value);

    $run = AgentRun::firstWhere('slug', $response->json('run_id'));

    expect($run->environment)->toBe(Environment::Staging)
        ->and($run->application_id)->toBe($agent->project->application_id)
        ->and($run->project_id)->toBe($agent->project_id);
});

test('a console run can route to an eligible fallback when the agent model is unavailable', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = playgroundAgent($team, [], Environment::Staging);
    $agent->project->application->update(['environment' => Environment::Production]);
    $agent->llmProvider->update(['environments' => [Environment::Production->value]]);
    $fallback = LlmProvider::factory()->for($team)->create([
        'provider' => 'OpenAI',
        'code' => 'gpt-5.4-mini',
        'status' => LlmStatus::Approved,
        'sensitivity' => Sensitivity::Internal,
        'environments' => [Environment::Staging->value],
        'input_cost' => 0.5,
        'output_cost' => 1.5,
    ]);
    ModelRoutingPolicy::factory()->for($team)->for($agent)->create([
        'fallback_provider_ids' => [$fallback->id],
    ]);
    bindFakeRouter()->textThen('Routed fallback run complete.');

    $response = $this->actingAs($owner)
        ->postJson(route('playground.runs.store', ['current_team' => $team->slug, 'agent' => $agent->slug]), playgroundRunPayload([
            'environment' => Environment::Staging->value,
            'input' => 'Run the staging project with a fallback model',
        ]))
        ->assertCreated()
        ->assertJsonPath('status', RunStatus::Completed->value);

    $run = AgentRun::firstWhere('slug', $response->json('run_id'));

    expect($run->environment)->toBe(Environment::Staging)
        ->and($run->llm_provider_id)->toBe($fallback->id);
});

test('a console run rejects an environment that does not match the selected project', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = playgroundAgent($team);

    $this->actingAs($owner)
        ->postJson(route('playground.runs.store', ['current_team' => $team->slug, 'agent' => $agent->slug]), playgroundRunPayload([
            'environment' => Environment::Development->value,
            'input' => 'Run in development',
        ]))
        ->assertStatus(422)
        ->assertJsonPath('message', 'The selected agent is not available in the Development playground environment.');

    expect(AgentRun::query()->count())->toBe(0);
});

test('a member of another team cannot run this team\'s agent (tenant isolation)', function () {
    [, $team] = ownerAndTeam();
    $agent = playgroundAgent($team);

    [$intruder, $intruderTeam] = ownerAndTeam();
    bindFakeRouter()->textThen('Nope.');

    // The intruder posts to their own team URL (passes membership) but targets
    // the victim team's agent slug — the policy must deny it.
    $this->actingAs($intruder)
        ->postJson(route('playground.runs.store', ['current_team' => $intruderTeam->slug, 'agent' => $agent->slug]), playgroundRunPayload([
            'input' => 'Steal data',
        ]))
        ->assertForbidden();
});

test('a frozen application blocks a console run', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = playgroundAgent($team);
    $agent->project->application->update([
        'runtime_frozen_at' => now(),
        'runtime_frozen_by' => $owner->getAuthIdentifier(),
    ]);
    bindFakeRouter()->textThen('Should not run.');

    $this->actingAs($owner)
        ->postJson(route('playground.runs.store', ['current_team' => $team->slug, 'agent' => $agent->slug]), playgroundRunPayload([
            'input' => 'Run me',
        ]))
        ->assertStatus(423);
});

test('a client-side tool pauses the console run, then a submitted result resumes it', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = playgroundAgent($team);
    assignPlaygroundClientTool($agent, $team);

    bindFakeRouter()
        ->toolCallThen('lookup-records', ['query' => 'today'])
        ->textThen('Found 3 records.');

    // First turn pauses for the client tool.
    $paused = $this->actingAs($owner)
        ->postJson(route('playground.runs.store', ['current_team' => $team->slug, 'agent' => $agent->slug]), playgroundRunPayload([
            'input' => 'Look up today',
        ]))
        ->assertCreated()
        ->assertJsonPath('status', RunStatus::WaitingForClient->value)
        ->assertJsonPath('tool_call.tool', 'lookup-records');

    $runId = $paused->json('run_id');
    $toolCallId = $paused->json('tool_call.id');

    // The console submits the client tool result and the run resumes to completion.
    $this->actingAs($owner)
        ->postJson(route('playground.runs.tool-result', ['current_team' => $team->slug, 'run' => $runId]), [
            'tool_call_id' => $toolCallId,
            'result' => ['total' => 3],
        ])
        ->assertOk()
        ->assertJsonPath('status', RunStatus::Completed->value)
        ->assertJsonPath('response', 'Found 3 records.');
});

test('the tool-result request validates its payload', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = playgroundAgent($team);
    assignPlaygroundClientTool($agent, $team);

    bindFakeRouter()->toolCallThen('lookup-records', ['query' => 'today'])->textThen('done');

    $paused = $this->actingAs($owner)
        ->postJson(route('playground.runs.store', ['current_team' => $team->slug, 'agent' => $agent->slug]), playgroundRunPayload([
            'input' => 'Look up today',
        ]))->assertCreated();

    $this->actingAs($owner)
        ->postJson(route('playground.runs.tool-result', ['current_team' => $team->slug, 'run' => $paused->json('run_id')]), [
            'tool_call_id' => '',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['tool_call_id', 'result']);
});

test('another team cannot resume this team\'s paused run', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = playgroundAgent($team);
    assignPlaygroundClientTool($agent, $team);
    bindFakeRouter()->toolCallThen('lookup-records', ['query' => 'today'])->textThen('done');

    $paused = $this->actingAs($owner)
        ->postJson(route('playground.runs.store', ['current_team' => $team->slug, 'agent' => $agent->slug]), playgroundRunPayload([
            'input' => 'Look up today',
        ]))->assertCreated();

    [$intruder, $intruderTeam] = ownerAndTeam();

    $this->actingAs($intruder)
        ->postJson(route('playground.runs.tool-result', ['current_team' => $intruderTeam->slug, 'run' => $paused->json('run_id')]), [
            'tool_call_id' => $paused->json('tool_call.id'),
            'result' => ['total' => 1],
        ])
        ->assertForbidden();
});

test('a MAACC-hosted tool call executes inline and the run completes in one console request', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = playgroundAgent($team);
    assignPlaygroundHostedTool($agent, $team);

    // The model asks MAACC to sum the numbers; the hosted handler runs for real,
    // then the model answers from the tool result — no client round-trip.
    bindFakeRouter()
        ->toolCallThen('sum', ['numbers' => [18432, 99211, 47]])
        ->textThen('The total is 117690.');

    $response = $this->actingAs($owner)
        ->postJson(route('playground.runs.store', ['current_team' => $team->slug, 'agent' => $agent->slug]), playgroundRunPayload([
            'input' => 'Add 18432, 99211 and 47 with the sum tool.',
        ]))
        ->assertCreated()
        ->assertJsonPath('status', RunStatus::Completed->value)
        ->assertJsonPath('response', 'The total is 117690.');

    $trace = collect($response->json('trace'));

    expect($trace->pluck('type'))->toContain('tool_required')
        ->and($trace->pluck('type'))->toContain('tool_result_received')
        ->and($trace->firstWhere('type', 'tool_result_received')['message'])->toContain('sum');
});
