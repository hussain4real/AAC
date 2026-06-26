<?php

use App\Enums\AgentStatus;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\LlmStatus;
use App\Enums\RunStatus;
use App\Enums\Sensitivity;
use App\Enums\ToolCallStatus;
use App\Enums\TraceEventType;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\Credential;
use App\Models\GovernanceSetting;
use App\Models\LlmProvider;
use App\Models\McpConnector;
use App\Models\Project;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use App\Support\Governance\PayloadMasker;
use App\Support\Runtime\Contracts\HostedTool;
use App\Support\Runtime\Contracts\LlmRouter;
use App\Support\Runtime\HostedTools\HostedToolRegistry;
use App\Support\Runtime\LlmCompletion;
use App\Support\Runtime\LlmRequest;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Passport\Passport;
use Tests\Support\Mcp\FakeMcpServer;
use Tests\Support\Runtime\FakeLlmRouter;

beforeEach(function () {
    [, $this->team] = ownerAndTeam();
    $this->application = Application::factory()->for($this->team)->create([
        'environment' => Environment::Production,
    ]);
    $this->project = Project::factory()->for($this->application)->create([
        'environment' => Environment::Production,
    ]);
    $this->provider = LlmProvider::factory()->for($this->team)->create([
        'provider' => 'Anthropic',
        'code' => 'anthropic/claude-3',
        'status' => LlmStatus::Approved,
        'environments' => [Environment::Production->value],
        'input_cost' => 1.0,
        'output_cost' => 2.0,
    ]);
    $this->agent = Agent::factory()->for($this->project)->published()->create([
        'llm_provider_id' => $this->provider->id,
        'agent_slug' => 'ops-summary',
        'system_prompt' => 'You summarize operations.',
    ]);
    $this->credential = Credential::factory()->for($this->application)->withOauthClient()->create([
        'environment' => Environment::Production,
    ]);

    Passport::actingAsClient($this->credential->oauthClient, [], 'api');
});

/**
 * Bind a deterministic fake LLM router for the current test.
 */
function fakeRouter(): FakeLlmRouter
{
    $fake = new FakeLlmRouter;
    app()->instance(LlmRouter::class, $fake);

    return $fake;
}

/**
 * Assign a tool contract to the test agent.
 *
 * @param  array<string, mixed>  $attributes
 */
function assignTool(array $attributes): ToolContract
{
    $tool = ToolContract::factory()
        ->for(test()->team)
        ->for(test()->application)
        ->create($attributes);

    ToolAssignment::factory()->forAgent(test()->agent)->create(['tool_contract_id' => $tool->id]);

    return $tool;
}

/**
 * Invoke the test agent's runtime endpoint.
 *
 * @param  array<string, mixed>  $payload
 */
function invokeAgent(array $payload = ['input' => 'Summarize today']): TestResponse
{
    return test()->postJson('/api/v1/agents/'.test()->agent->agent_slug.'/runs', $payload);
}

test('a no-tool run completes and returns the response and usage', function () {
    fakeRouter()->textThen('All vessels are on schedule.', tokensIn: 200, tokensOut: 80);

    $response = invokeAgent(['input' => 'Status?', 'caller' => 'ops-bot'])->assertCreated();

    $response->assertJsonStructure(['run_id', 'status', 'response', 'cost', 'usage' => ['tokens_in', 'tokens_out']])
        ->assertJsonPath('status', RunStatus::Completed->value)
        ->assertJsonPath('response', 'All vessels are on schedule.')
        ->assertJsonPath('agent_slug', 'ops-summary')
        ->assertJsonPath('usage.tokens_in', 200)
        ->assertJsonPath('usage.tokens_out', 80);

    expect($response->json('run_id'))->toStartWith('run_');

    $run = AgentRun::firstWhere('slug', $response->json('run_id'));

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->caller)->toBe('ops-bot')
        ->and($run->output)->toBe('All vessels are on schedule.')
        ->and($run->cost)->toBeGreaterThan(0)
        ->and($run->traceEvents()->pluck('type')->all())->toContain(
            TraceEventType::RunRequested,
            TraceEventType::CallerAuthenticated,
            TraceEventType::ModelSelected,
            TraceEventType::PromptPrepared,
            TraceEventType::Completed,
        );
});

test('the system prompt sent to the model is the user prompt plus the auto-generated tool brief', function () {
    assignTool([
        'slug' => 'getRecords',
        'name' => 'Get Records',
        'description' => 'Fetches operational voyage records.',
        'execution_mode' => ExecMode::Client,
    ]);

    $fake = fakeRouter();
    $fake->textThen('Done.');

    invokeAgent(['input' => 'Status?'])->assertCreated();

    expect($fake->requests[0]->systemPrompt)
        ->toContain('You summarize operations.')
        ->toContain('## Tools available to you')
        ->toContain('`getRecords` (Get Records)')
        ->toContain('Fetches operational voyage records.');
});

test('a run that needs a client-side tool pauses and returns the tool request', function () {
    $tool = assignTool(['slug' => 'getRecords', 'execution_mode' => ExecMode::Client]);

    fakeRouter()->toolCallThen('getRecords', ['query' => 'today']);

    $response = invokeAgent()->assertCreated();

    $response->assertJsonPath('status', RunStatus::WaitingForClient->value)
        ->assertJsonPath('tool_call.tool', 'getRecords')
        ->assertJsonPath('tool_call.arguments.query', 'today')
        ->assertJsonPath('tool_call.output_schema', $tool->output_schema);

    $run = AgentRun::firstWhere('slug', $response->json('run_id'));

    expect($run->status)->toBe(RunStatus::WaitingForClient)
        ->and($run->pendingToolCalls()->count())->toBe(1)
        ->and($run->tools)->toBe(['getRecords']);
});

test('a paused run resumes and completes when a valid tool result is submitted', function () {
    assignTool(['slug' => 'getRecords', 'execution_mode' => ExecMode::Client]);

    fakeRouter()
        ->toolCallThen('getRecords', ['query' => 'today'])
        ->textThen('There were 12 departures.');

    $start = invokeAgent()->assertCreated();
    $runId = $start->json('run_id');
    $toolCallId = $start->json('tool_call.id');

    $resume = test()->postJson("/api/v1/runs/{$runId}/tool-results", [
        'tool_call_id' => $toolCallId,
        'result' => ['results' => ['a', 'b'], 'total' => 12],
    ])->assertOk();

    $resume->assertJsonPath('status', RunStatus::Completed->value)
        ->assertJsonPath('response', 'There were 12 departures.');

    $run = AgentRun::firstWhere('slug', $runId);

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->toolCalls()->first()->status)->toBe(ToolCallStatus::Completed)
        ->and($run->traceEvents()->pluck('type')->all())->toContain(
            TraceEventType::ToolResultReceived,
            TraceEventType::Validated,
            TraceEventType::Resumed,
        );
});

test('an invalid tool result is rejected and the run stays waiting', function () {
    assignTool(['slug' => 'getRecords', 'execution_mode' => ExecMode::Client]);

    fakeRouter()->toolCallThen('getRecords', ['query' => 'today']);

    $start = invokeAgent()->assertCreated();
    $runId = $start->json('run_id');

    test()->postJson("/api/v1/runs/{$runId}/tool-results", [
        'tool_call_id' => $start->json('tool_call.id'),
        'result' => ['total' => 5], // missing required "results"
    ])
        ->assertStatus(422)
        ->assertJsonPath('error', 'invalid_tool_result');

    $run = AgentRun::firstWhere('slug', $runId);

    expect($run->status)->toBe(RunStatus::WaitingForClient)
        ->and($run->traceEvents()->where('type', TraceEventType::Failed)->count())->toBe(1);
});

test('an oversized tool result is rejected and is recorded in the trace', function () {
    assignTool([
        'slug' => 'getRecords',
        'execution_mode' => ExecMode::Client,
        'max_payload_kb' => 1,
    ]);

    fakeRouter()->toolCallThen('getRecords', ['query' => 'today']);

    $start = invokeAgent()->assertCreated();
    $runId = $start->json('run_id');

    test()->postJson("/api/v1/runs/{$runId}/tool-results", [
        'tool_call_id' => $start->json('tool_call.id'),
        'result' => ['results' => [str_repeat('x', 4000)], 'total' => 1],
    ])
        ->assertStatus(413)
        ->assertJsonPath('error', 'payload_too_large');

    $run = AgentRun::firstWhere('slug', $runId);

    // Fails safely: the run stays paused (resubmittable) and the rejection is audited.
    expect($run->status)->toBe(RunStatus::WaitingForClient)
        ->and($run->traceEvents()->where('type', TraceEventType::Failed)->get()
            ->contains(fn ($event) => ($event->data['code'] ?? null) === 'payload_too_large'))->toBeTrue();
});

test('submitting a result with an unknown tool call id is rejected', function () {
    assignTool(['slug' => 'getRecords', 'execution_mode' => ExecMode::Client]);

    fakeRouter()->toolCallThen('getRecords', ['query' => 'today']);

    $start = invokeAgent()->assertCreated();

    test()->postJson('/api/v1/runs/'.$start->json('run_id').'/tool-results', [
        'tool_call_id' => 'does-not-exist',
        'result' => ['results' => [], 'total' => 0],
    ])
        ->assertStatus(409)
        ->assertJsonPath('error', 'run_not_waiting');
});

test('submitting a result to a run that is not waiting is rejected', function () {
    fakeRouter()->textThen('Done.');

    $start = invokeAgent()->assertCreated();

    test()->postJson('/api/v1/runs/'.$start->json('run_id').'/tool-results', [
        'tool_call_id' => 'whatever',
        'result' => ['results' => [], 'total' => 0],
    ])
        ->assertStatus(409)
        ->assertJsonPath('error', 'run_not_waiting');
});

test('submitting a tool result for another application run is rejected', function () {
    $otherApp = Application::factory()->for($this->team)->create();
    $foreignRun = AgentRun::factory()->for($otherApp)->waitingForClient()->create();

    test()->postJson("/api/v1/runs/{$foreignRun->slug}/tool-results", [
        'tool_call_id' => 'whatever',
        'result' => ['results' => [], 'total' => 0],
    ])
        ->assertStatus(404)
        ->assertJsonPath('error', 'run_not_found');
});

test('a hosted tool executes inline and the run completes', function () {
    assignTool([
        'slug' => 'echo',
        'execution_mode' => ExecMode::Hosted,
        'input_schema' => ['message' => 'string'],
        'output_schema' => ['message' => 'string'],
    ]);

    fakeRouter()
        ->toolCallThen('echo', ['message' => 'ping'])
        ->textThen('The echo said ping.');

    $response = invokeAgent()->assertCreated();

    $response->assertJsonPath('status', RunStatus::Completed->value)
        ->assertJsonPath('response', 'The echo said ping.');

    $run = AgentRun::firstWhere('slug', $response->json('run_id'));
    $call = $run->toolCalls()->first();

    expect($call->status)->toBe(ToolCallStatus::Completed)
        ->and($call->result)->toBe(['message' => 'ping']);
});

test('a run fails when the model requests an unknown tool', function () {
    fakeRouter()->toolCallThen('ghost', []);

    $response = invokeAgent()->assertCreated();

    $response->assertJsonPath('status', RunStatus::Failed->value)
        ->assertJsonPath('error', fn ($error) => str_contains((string) $error, 'not assigned'));
});

test('a run fails when the model produces invalid tool arguments', function () {
    assignTool(['slug' => 'getRecords', 'execution_mode' => ExecMode::Client]);

    fakeRouter()->toolCallThen('getRecords', []); // missing required "query"

    $response = invokeAgent()->assertCreated();

    $response->assertJsonPath('status', RunStatus::Failed->value);

    $run = AgentRun::firstWhere('slug', $response->json('run_id'));

    expect($run->toolCalls()->first()->status)->toBe(ToolCallStatus::Failed)
        ->and($run->traceEvents()->where('type', TraceEventType::Failed)->exists())->toBeTrue();
});

test('a run fails when a hosted tool has no registered handler', function () {
    assignTool([
        'slug' => 'mystery_tool',
        'execution_mode' => ExecMode::Hosted,
        'input_schema' => ['note' => 'string?'],
        'output_schema' => ['ok' => 'boolean'],
    ]);

    fakeRouter()->toolCallThen('mystery_tool', []);

    invokeAgent()->assertCreated()
        ->assertJsonPath('status', RunStatus::Failed->value)
        ->assertJsonPath('error', fn ($error) => str_contains((string) $error, 'No hosted handler'));
});

test('a run fails when a hosted tool throws', function () {
    app(HostedToolRegistry::class)->register('boom', new class implements HostedTool
    {
        public function handle(array $arguments): array
        {
            throw new RuntimeException('hosted blew up');
        }
    });

    assignTool([
        'slug' => 'boom',
        'execution_mode' => ExecMode::Hosted,
        'input_schema' => ['note' => 'string?'],
        'output_schema' => ['ok' => 'boolean'],
    ]);

    fakeRouter()->toolCallThen('boom', []);

    invokeAgent()->assertCreated()
        ->assertJsonPath('status', RunStatus::Failed->value)
        ->assertJsonPath('error', 'hosted blew up');
});

test('a run fails when a hosted tool returns invalid output', function () {
    assignTool([
        'slug' => 'echo',
        'execution_mode' => ExecMode::Hosted,
        'input_schema' => ['message' => 'string'],
        'output_schema' => ['count' => 'number'], // echo never returns "count"
    ]);

    fakeRouter()->toolCallThen('echo', ['message' => 'hi']);

    invokeAgent()->assertCreated()
        ->assertJsonPath('status', RunStatus::Failed->value)
        ->assertJsonPath('error', fn ($error) => str_contains((string) $error, 'does not satisfy'));
});

test('a run fails for an unsupported execution mode', function () {
    assignTool([
        'slug' => 'db_lookup',
        'execution_mode' => ExecMode::Db,
        'input_schema' => ['q' => 'string?'],
    ]);

    fakeRouter()->toolCallThen('db_lookup', []);

    invokeAgent()->assertCreated()
        ->assertJsonPath('status', RunStatus::Failed->value)
        ->assertJsonPath('error', fn ($error) => str_contains((string) $error, 'not supported'));
});

test('a run fails safely when the model call errors', function () {
    app()->instance(LlmRouter::class, new class implements LlmRouter
    {
        public function complete(LlmRequest $request): LlmCompletion
        {
            throw new RuntimeException('provider unreachable');
        }
    });

    $response = invokeAgent()->assertCreated();

    $response->assertJsonPath('status', RunStatus::Failed->value)
        ->assertJsonPath('error', 'provider unreachable');
});

test('a run fails when the agent model is not available in the environment', function () {
    $this->provider->update(['status' => LlmStatus::Deprecated]);

    fakeRouter()->textThen('unused');

    $response = invokeAgent()->assertCreated();

    $response->assertJsonPath('status', RunStatus::Failed->value)
        ->assertJsonPath('error', fn ($error) => str_contains((string) $error, 'not approved'));

    $run = AgentRun::firstWhere('slug', $response->json('run_id'));

    expect($run->traceEvents()->where('type', TraceEventType::ModelSelected)->exists())->toBeFalse();
});

test('a run is cancelled when the agent is unpublished before resuming', function () {
    assignTool(['slug' => 'getRecords', 'execution_mode' => ExecMode::Client]);

    fakeRouter()
        ->toolCallThen('getRecords', ['query' => 'today'])
        ->textThen('never reached');

    $start = invokeAgent()->assertCreated();
    $runId = $start->json('run_id');

    $this->agent->update(['status' => AgentStatus::Disabled]);

    $resume = test()->postJson("/api/v1/runs/{$runId}/tool-results", [
        'tool_call_id' => $start->json('tool_call.id'),
        'result' => ['results' => [], 'total' => 0],
    ])->assertOk();

    $resume->assertJsonPath('status', RunStatus::Cancelled->value);
});

test('a run that exceeds the step limit fails', function () {
    config(['maac.runtime.max_steps' => 1]);

    assignTool([
        'slug' => 'echo',
        'execution_mode' => ExecMode::Hosted,
        'input_schema' => ['message' => 'string'],
        'output_schema' => ['message' => 'string'],
    ]);

    fakeRouter()->toolCallThen('echo', ['message' => 'loop']); // repeats forever

    invokeAgent()->assertCreated()
        ->assertJsonPath('status', RunStatus::Failed->value)
        ->assertJsonPath('error', fn ($error) => str_contains((string) $error, 'maximum number of steps'));
});

test('a run expires immediately when started past its deadline', function () {
    config(['maac.runtime.default_timeout_seconds' => -10]);

    fakeRouter()->textThen('too late');

    invokeAgent()->assertCreated()
        ->assertJsonPath('status', RunStatus::Expired->value);
});

test('a paused run expires when resumed after its deadline', function () {
    assignTool(['slug' => 'getRecords', 'execution_mode' => ExecMode::Client]);

    fakeRouter()->toolCallThen('getRecords', ['query' => 'today']);

    $start = invokeAgent()->assertCreated();
    $runId = $start->json('run_id');

    $this->travel(5)->minutes();

    $resume = test()->postJson("/api/v1/runs/{$runId}/tool-results", [
        'tool_call_id' => $start->json('tool_call.id'),
        'result' => ['results' => [], 'total' => 0],
    ])->assertOk();

    $resume->assertJsonPath('status', RunStatus::Expired->value);

    $run = AgentRun::firstWhere('slug', $runId);

    // A late result fails safely and the expiry is recorded in the trace.
    expect($run->traceEvents()->where('type', TraceEventType::Failed)->get()
        ->contains(fn ($event) => ($event->data['code'] ?? null) === 'run_expired'))->toBeTrue();
});

test('run status can be retrieved and expires lazily on read', function () {
    fakeRouter()->textThen('Done.');
    $completed = invokeAgent()->assertCreated();

    test()->getJson('/api/v1/runs/'.$completed->json('run_id'))
        ->assertOk()
        ->assertJsonPath('status', RunStatus::Completed->value);

    // A run still in progress past its deadline expires when its status is read.
    $stale = AgentRun::factory()->for($this->agent)->for($this->project)->for($this->application)->create([
        'status' => RunStatus::WaitingForClient,
        'expires_at' => now()->subMinute(),
        'started_at' => now()->subMinutes(5),
    ]);

    test()->getJson("/api/v1/runs/{$stale->slug}")
        ->assertOk()
        ->assertJsonPath('status', RunStatus::Expired->value);
});

test('retrieving an unknown run returns not found', function () {
    test()->getJson('/api/v1/runs/run_missing')
        ->assertStatus(404)
        ->assertJsonPath('error', 'run_not_found');
});

test('a run owned by another application cannot be retrieved', function () {
    $otherApp = Application::factory()->for($this->team)->create();
    $foreignRun = AgentRun::factory()->for($otherApp)->create();

    test()->getJson("/api/v1/runs/{$foreignRun->slug}")
        ->assertStatus(404)
        ->assertJsonPath('error', 'run_not_found');
});

test('invoking an unpublished agent is rejected', function () {
    $this->agent->update(['status' => AgentStatus::Draft]);

    invokeAgent()
        ->assertStatus(409)
        ->assertJsonPath('error', 'agent_not_published');
});

test('invoking an unknown agent is rejected', function () {
    test()->postJson('/api/v1/agents/no-such-agent/runs', ['input' => 'hi'])
        ->assertStatus(404)
        ->assertJsonPath('error', 'agent_not_found');
});

test('an agent belonging to another application cannot be invoked', function () {
    $otherApp = Application::factory()->for($this->team)->create();
    $otherProject = Project::factory()->for($otherApp)->create();
    $foreignAgent = Agent::factory()->for($otherProject)->published()->create(['agent_slug' => 'foreign']);

    test()->postJson("/api/v1/agents/{$foreignAgent->agent_slug}/runs", ['input' => 'hi'])
        ->assertStatus(404)
        ->assertJsonPath('error', 'agent_not_found');
});

test('the runtime validates the invocation payload', function () {
    invokeAgent(['input' => ''])->assertStatus(422)->assertJsonValidationErrors('input');
});

test('confidential run input and tool results are masked at rest', function () {
    // The run inherits the most sensitive assigned tool's classification.
    assignTool(['slug' => 'getRecords', 'execution_mode' => ExecMode::Client, 'sensitivity' => Sensitivity::Confidential]);

    fakeRouter()
        ->toolCallThen('getRecords', ['query' => 'today'])
        ->textThen('done');

    $start = invokeAgent(['input' => 'sensitive prompt'])->assertCreated();
    $runId = $start->json('run_id');

    // The live pause response still exposes the real arguments for execution.
    expect($start->json('tool_call.arguments.query'))->toBe('today');

    $run = AgentRun::firstWhere('slug', $runId);

    expect($run->sensitivity)->toBe(Sensitivity::Confidential)
        ->and($run->masked)->toBeTrue()
        ->and($run->input)->toBe(PayloadMasker::REDACTED);

    test()->postJson("/api/v1/runs/{$runId}/tool-results", [
        'tool_call_id' => $start->json('tool_call.id'),
        'result' => ['results' => ['x'], 'total' => 1],
    ])->assertOk()->assertJsonPath('response', 'done');

    $call = AgentRun::firstWhere('slug', $runId)->toolCalls()->first();

    expect($call->result['results'])->toBe([PayloadMasker::REDACTED])
        ->and($call->result['total'])->toBe(PayloadMasker::REDACTED);
});

test('a run is rejected when the daily run quota is exceeded', function () {
    GovernanceSetting::factory()->for($this->team)->create(['default_daily_run_quota' => 1]);

    AgentRun::factory()->create([
        'agent_id' => $this->agent->id,
        'project_id' => $this->project->id,
        'application_id' => $this->application->id,
        'llm_provider_id' => $this->provider->id,
        'environment' => Environment::Production,
        'started_at' => now(),
    ]);

    invokeAgent()
        ->assertStatus(429)
        ->assertJsonPath('error', 'quota_exceeded');
});

test('a run completes using a remote HTTP tool executed by MAAC', function () {
    config(['maac.runtime.remote_http.allowed_hosts' => ['tools.example.com']]);
    Http::preventStrayRequests();
    Http::fake(['tools.example.com/*' => Http::response(['result' => 'on time', 'total' => 3])]);

    assignTool([
        'slug' => 'fleet_status',
        'execution_mode' => ExecMode::Http,
        'input_schema' => ['q' => 'string?'],
        'output_schema' => ['result' => 'string', 'total' => 'number'],
        'http_config' => [
            'method' => 'post',
            'endpoint' => 'https://tools.example.com/fleet',
            'auth' => ['type' => 'none'],
            'retry' => ['max_attempts' => 1, 'backoff_ms' => 0],
        ],
    ]);

    fakeRouter()->toolCallThen('fleet_status', ['q' => 'today'])->textThen('All 3 vessels on time.');

    $response = invokeAgent()->assertCreated();
    $response->assertJsonPath('status', RunStatus::Completed->value)
        ->assertJsonPath('response', 'All 3 vessels on time.');

    $run = AgentRun::firstWhere('slug', $response->json('run_id'));
    $call = $run->toolCalls()->first();

    expect($call->status)->toBe(ToolCallStatus::Completed)
        ->and($call->result)->toBe(['result' => 'on time', 'total' => 3])
        ->and($run->traceEvents()->where('type', TraceEventType::ToolResultReceived)->get()
            ->contains(fn ($event) => ($event->data['execution_mode'] ?? null) === 'http'))->toBeTrue();
});

test('a run completes using an MCP connector tool executed by MAAC', function () {
    Http::preventStrayRequests();
    Http::fake(FakeMcpServer::make()->returns('lookup', ['result' => 'Doha'])->handler());

    $connector = McpConnector::factory()->for($this->team)->create([
        'server_url' => 'https://mcp.example.com/mcp',
        'environments' => [Environment::Production->value],
    ]);

    assignTool([
        'slug' => 'port_lookup',
        'execution_mode' => ExecMode::Connector,
        'mcp_connector_id' => $connector->id,
        'mcp_tool_name' => 'lookup',
        'input_schema' => ['q' => 'string?'],
        'output_schema' => ['result' => 'string'],
    ]);

    fakeRouter()->toolCallThen('port_lookup', ['q' => 'home'])->textThen('The port is Doha.');

    $response = invokeAgent()->assertCreated();
    $response->assertJsonPath('status', RunStatus::Completed->value)
        ->assertJsonPath('response', 'The port is Doha.');

    $run = AgentRun::firstWhere('slug', $response->json('run_id'));
    $call = $run->toolCalls()->first();

    expect($call->result)->toBe(['result' => 'Doha'])
        ->and($run->traceEvents()->where('type', TraceEventType::ToolResultReceived)->get()
            ->contains(fn ($event) => ($event->data['connector'] ?? null) === $connector->slug
                && ($event->data['remote_tool'] ?? null) === 'lookup'))->toBeTrue();
});

test('a remote HTTP tool result is redacted at rest by the tool redaction rules', function () {
    config(['maac.runtime.remote_http.allowed_hosts' => ['tools.example.com']]);
    Http::preventStrayRequests();
    Http::fake(['tools.example.com/*' => Http::response(['result' => 'ok', 'ssn' => '123-45-6789'])]);

    assignTool([
        'slug' => 'lookup_customer',
        'execution_mode' => ExecMode::Http,
        'input_schema' => ['q' => 'string?'],
        'output_schema' => ['result' => 'string', 'ssn' => 'string'],
        'http_config' => [
            'method' => 'post',
            'endpoint' => 'https://tools.example.com/customer',
            'auth' => ['type' => 'none'],
            'retry' => ['max_attempts' => 1, 'backoff_ms' => 0],
        ],
        'redaction' => ['ssn'],
    ]);

    fakeRouter()->toolCallThen('lookup_customer', [])->textThen('done');

    $response = invokeAgent()->assertCreated();
    $run = AgentRun::firstWhere('slug', $response->json('run_id'));

    // The at-rest copy is redacted; the model still received the raw value to complete.
    expect($run->toolCalls()->first()->result)->toBe(['result' => 'ok', 'ssn' => '[redacted]'])
        ->and($run->status)->toBe(RunStatus::Completed);
});

test('a remote HTTP run fails with a controlled error for a blocked endpoint', function () {
    config(['maac.runtime.remote_http.allowed_hosts' => []]);

    assignTool([
        'slug' => 'blocked_tool',
        'execution_mode' => ExecMode::Http,
        'input_schema' => ['q' => 'string?'],
        'output_schema' => ['result' => 'string'],
        'http_config' => [
            'method' => 'post',
            'endpoint' => 'https://blocked.example.org/x',
            'auth' => ['type' => 'none'],
            'retry' => ['max_attempts' => 1, 'backoff_ms' => 0],
        ],
    ]);

    fakeRouter()->toolCallThen('blocked_tool', []);

    $response = invokeAgent()->assertCreated();
    $response->assertJsonPath('status', RunStatus::Failed->value);

    $run = AgentRun::firstWhere('slug', $response->json('run_id'));

    expect($run->failure_reason)->toBe('remote_http_blocked')
        ->and($run->toolCalls()->first()->status)->toBe(ToolCallStatus::Failed)
        ->and($run->traceEvents()->where('type', TraceEventType::Failed)->get()
            ->contains(fn ($event) => ($event->data['code'] ?? null) === 'remote_http_blocked'))->toBeTrue();
});

test('a connector run fails with a controlled error when the server is unreachable', function () {
    Http::preventStrayRequests();
    Http::fake(fn () => throw new ConnectionException('Connection refused.'));

    $connector = McpConnector::factory()->for($this->team)->create([
        'environments' => [Environment::Production->value],
    ]);

    assignTool([
        'slug' => 'port_lookup',
        'execution_mode' => ExecMode::Connector,
        'mcp_connector_id' => $connector->id,
        'mcp_tool_name' => 'lookup',
        'input_schema' => ['q' => 'string?'],
        'output_schema' => ['result' => 'string'],
    ]);

    fakeRouter()->toolCallThen('port_lookup', []);

    $response = invokeAgent()->assertCreated();
    $response->assertJsonPath('status', RunStatus::Failed->value);

    expect(AgentRun::firstWhere('slug', $response->json('run_id'))->failure_reason)->toBe('connector_unreachable');
});

test('a server-side tool requiring approval is blocked at runtime until activated', function () {
    config(['maac.runtime.remote_http.allowed_hosts' => ['tools.example.com']]);
    Http::preventStrayRequests();
    Http::fake(['tools.example.com/*' => Http::response(['result' => 'ok'])]);

    assignTool([
        'slug' => 'gated_tool',
        'execution_mode' => ExecMode::Http,
        'requires_approval' => true,
        'status' => 'Draft',
        'input_schema' => ['q' => 'string?'],
        'output_schema' => ['result' => 'string'],
        'http_config' => [
            'method' => 'post',
            'endpoint' => 'https://tools.example.com/gated',
            'auth' => ['type' => 'none'],
            'retry' => ['max_attempts' => 1, 'backoff_ms' => 0],
        ],
    ]);

    fakeRouter()->toolCallThen('gated_tool', []);

    $response = invokeAgent()->assertCreated();
    $response->assertJsonPath('status', RunStatus::Failed->value);

    expect(AgentRun::firstWhere('slug', $response->json('run_id'))->failure_reason)->toBe('tool_requires_approval');
    Http::assertNothingSent();
});

test('a connector tool requiring approval is blocked at runtime until activated', function () {
    Http::preventStrayRequests();
    Http::fake(FakeMcpServer::make()->returns('lookup', ['result' => 'x'])->handler());

    $connector = McpConnector::factory()->for($this->team)->create([
        'environments' => [Environment::Production->value],
    ]);

    assignTool([
        'slug' => 'gated_connector',
        'execution_mode' => ExecMode::Connector,
        'requires_approval' => true,
        'status' => 'Draft',
        'mcp_connector_id' => $connector->id,
        'mcp_tool_name' => 'lookup',
        'input_schema' => ['q' => 'string?'],
        'output_schema' => ['result' => 'string'],
    ]);

    fakeRouter()->toolCallThen('gated_connector', []);

    $response = invokeAgent()->assertCreated();
    $response->assertJsonPath('status', RunStatus::Failed->value);

    expect(AgentRun::firstWhere('slug', $response->json('run_id'))->failure_reason)->toBe('tool_requires_approval');
    Http::assertNothingSent();
});
