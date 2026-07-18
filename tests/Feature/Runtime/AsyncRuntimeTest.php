<?php

use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Enums\LlmStatus;
use App\Enums\RunMode;
use App\Enums\RunStatus;
use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEndpointStatus;
use App\Enums\WebhookEventType;
use App\Jobs\AdvanceAgentRun;
use App\Jobs\DeliverWebhook;
use App\Jobs\ProcessAgentRun;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\Credential;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Runtime\AgentRunner;
use App\Support\Runtime\Routing\ModelRouter;
use App\Support\Runtime\Routing\RoutingDecision;
use App\Support\Runtime\StreamConcurrencyLimiter;
use App\Support\Webhooks\WebhookSigner;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Laravel\Passport\Passport;

use function Pest\Laravel\mock;

beforeEach(function () {
    [, $this->team] = ownerAndTeam();
    $this->application = Application::factory()->for($this->team)->create(['environment' => Environment::Production]);
    $this->project = Project::factory()->for($this->application)->create(['environment' => Environment::Production]);
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
 * Register a webhook endpoint for the test application.
 *
 * @param  array<string, mixed>  $attributes
 */
function webhookEndpoint(array $attributes = []): WebhookEndpoint
{
    return WebhookEndpoint::factory()->for(test()->application)->create([
        'environment' => Environment::Production,
        ...$attributes,
    ]);
}

/**
 * Assign a client-side tool contract to the test agent.
 */
function assignClientTool(string $slug = 'getRecords'): ToolContract
{
    $tool = ToolContract::factory()->for(test()->team)->for(test()->application)->create([
        'slug' => $slug,
        'execution_mode' => ExecMode::Client,
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['records' => 'array', 'total' => 'number'],
    ]);

    ToolAssignment::factory()->forAgent(test()->agent)->create(['tool_contract_id' => $tool->id]);
    ToolImplementation::factory()->for($tool)->for(test()->application)->create([
        'environment' => Environment::Production,
        'status' => ImplStatus::Implemented,
        'implemented_version' => $tool->version,
        'schema_fingerprint' => $tool->schemaFingerprint(),
    ]);
    approveCurrentAgentConfiguration(test()->agent);

    return $tool;
}

test('an async run is queued immediately and driven by a worker', function () {
    Queue::fake();

    $response = test()->postJson('/api/v1/agents/ops-summary/runs', ['input' => 'Status?', 'mode' => 'async'])
        ->assertStatus(202)
        ->assertJsonPath('status', RunStatus::Queued->value);

    $run = AgentRun::firstWhere('slug', $response->json('run_id'));

    expect($run->mode)->toBe(RunMode::Async)
        ->and($run->status)->toBe(RunStatus::Queued);

    Queue::assertPushed(ProcessAgentRun::class, fn (ProcessAgentRun $job): bool => $job->run->is($run));
});

test('an async run completes end to end through the queue', function () {
    bindFakeRouter()->textThen('All vessels on schedule.', tokensIn: 120, tokensOut: 40);

    $response = test()->postJson('/api/v1/agents/ops-summary/runs', ['input' => 'Status?', 'mode' => 'async'])
        ->assertStatus(202);

    // With the sync queue the worker ran inline, so the run has completed.
    $run = AgentRun::firstWhere('slug', $response->json('run_id'));

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->output)->toBe('All vessels on schedule.')
        ->and($run->cost)->toBeGreaterThan(0);
});

test('a queued run fails when readiness changes before worker processing', function () {
    approveCurrentAgentConfiguration($this->agent);
    $runner = app(AgentRunner::class);
    $run = $runner->createRun(
        $this->agent,
        $this->application,
        Environment::Production,
        'Status?',
        null,
        RunMode::Async,
    );
    $this->application->update(['status' => 'suspended']);

    $processed = $runner->process($run);

    expect($processed->status)->toBe(RunStatus::Failed)
        ->and($processed->failure_reason)->toBe('agent_not_ready');
});

test('a queued run fails when routing has no eligible provider', function () {
    approveCurrentAgentConfiguration($this->agent);
    $router = mock(ModelRouter::class);
    $router->shouldReceive('select')->once()->andReturn(new RoutingDecision(
        null,
        [],
        [],
        null,
        'No eligible model fixture.',
    ));
    $this->app->instance(ModelRouter::class, $router);
    $runner = app(AgentRunner::class);
    $run = $runner->createRun(
        $this->agent,
        $this->application,
        Environment::Production,
        'Status?',
        null,
        RunMode::Async,
    );

    $processed = $runner->process($run);

    expect($processed->status)->toBe(RunStatus::Failed)
        ->and($processed->failure_reason)->toBe('model_unavailable');
});

test('a queued run is cancelled when its agent is unpublished before driving', function () {
    approveCurrentAgentConfiguration($this->agent);
    bindFakeRouter()->textThen('unused');
    $runner = app(AgentRunner::class);
    $run = $runner->createRun(
        $this->agent,
        $this->application,
        Environment::Production,
        'Status?',
        null,
        RunMode::Async,
    );
    $this->agent->update(['status' => 'draft']);
    $run->unsetRelation('agent');

    $driven = $runner->drive($run);

    expect($driven->status)->toBe(RunStatus::Cancelled)
        ->and($driven->failure_reason)->toBe('agent_unpublished');
});

test('an async run pauses for a client tool and resumes via the queue', function () {
    assignClientTool('getRecords');
    bindFakeRouter()->toolCallThen('getRecords', ['query' => 'today'])->textThen('Done.');

    $start = test()->postJson('/api/v1/agents/ops-summary/runs', ['input' => 'Go', 'mode' => 'async'])->assertStatus(202);

    $run = AgentRun::firstWhere('slug', $start->json('run_id'));
    expect($run->status)->toBe(RunStatus::WaitingForClient);

    $call = $run->pendingToolCalls()->first();

    Queue::fake();
    test()->postJson("/api/v1/runs/{$run->slug}/tool-results", [
        'tool_call_id' => $call->id,
        'result' => ['records' => ['a'], 'total' => 1],
    ])->assertStatus(202)->assertJsonPath('status', RunStatus::Running->value);

    Queue::assertPushed(AdvanceAgentRun::class, fn (AdvanceAgentRun $job): bool => $job->run->is($run->fresh()));
});

test('the run stream replays trace events and ends with the final state', function () {
    bindFakeRouter()->textThen('Streamed answer.');

    $response = test()->postJson('/api/v1/agents/ops-summary/runs', ['input' => 'Status?'])->assertCreated();
    $runId = $response->json('run_id');

    $stream = test()->get("/api/v1/runs/{$runId}/stream");
    $stream->assertOk();

    $content = $stream->streamedContent();

    expect($content)->toContain('event: run.event')
        ->toContain('run_requested')
        ->toContain('completed')
        ->toContain('event: run.state');
});

test('the run stream tails a still-running run up to its budget', function () {
    config(['maacc.runtime.stream.poll_interval_ms' => 5, 'maacc.runtime.stream.max_seconds' => 0.01]);

    $run = AgentRun::factory()->for($this->agent)->for($this->application)->for($this->project)->create([
        'status' => RunStatus::Queued,
        'mode' => RunMode::Async,
        'environment' => Environment::Production,
        'expires_at' => now()->addMinutes(5),
    ]);

    $content = test()->get("/api/v1/runs/{$run->slug}/stream")->assertOk()->streamedContent();

    expect($content)->toContain('event: run.state')->toContain(RunStatus::Queued->value);
});

test('the run stream applies application-scoped concurrency backpressure', function () {
    config(['maacc.runtime.stream.max_concurrent_per_application' => 1]);

    $run = AgentRun::factory()->for($this->agent)->for($this->application)->for($this->project)->create([
        'status' => RunStatus::Queued,
        'mode' => RunMode::Async,
        'environment' => Environment::Production,
        'expires_at' => now()->addMinutes(5),
    ]);

    $lease = app(StreamConcurrencyLimiter::class)->acquire($this->application->id);
    expect($lease)->not->toBeNull();

    try {
        test()->getJson("/api/v1/runs/{$run->slug}/stream")
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '2')
            ->assertHeader('X-MAACC-Backpressure', 'stream-limit')
            ->assertJsonPath('error', 'stream_concurrency_exceeded');
    } finally {
        $lease?->release();
    }
});

test('the public API rejects oversized envelopes before execution', function () {
    config(['maacc.runtime.gateway.max_body_kb' => 1]);

    test()->postJson('/api/v1/agents/ops-summary/runs', ['input' => str_repeat('x', 2048)])
        ->assertStatus(413)
        ->assertJsonPath('error', 'request_body_too_large');
});

test('the public API applies application-scoped concurrency backpressure', function () {
    config(['maacc.runtime.api_concurrency.run' => 1]);
    $lease = Cache::lock("maacc:api-concurrency:{$this->application->id}:run:1", 30);
    expect($lease->get())->toBeTrue();

    try {
        test()->postJson('/api/v1/agents/ops-summary/runs', ['input' => 'Status?'])
            ->assertTooManyRequests()
            ->assertHeader('Retry-After', '1')
            ->assertHeader('X-MAACC-Backpressure', 'api-concurrency')
            ->assertJsonPath('error', 'api_concurrency_exceeded');
    } finally {
        $lease->release();
    }
});

test('an application registers, lists, and deletes a webhook endpoint', function () {
    $register = test()->postJson('/api/v1/webhook-endpoints', [
        'url' => 'https://app.example.com/hooks/maacc',
        'events' => [WebhookEventType::RunCompleted->value],
    ])->assertStatus(201);

    $register->assertJsonStructure(['id', 'url', 'events', 'environment', 'status', 'secret']);
    expect($register->json('secret'))->toStartWith('whsec_')
        ->and($register->json('status'))->toBe('pending_verification');

    $id = $register->json('id');

    test()->getJson('/api/v1/webhook-endpoints')
        ->assertOk()
        ->assertJsonPath('data.0.id', $id)
        ->assertJsonMissingPath('data.0.secret');

    test()->deleteJson("/api/v1/webhook-endpoints/{$id}")->assertNoContent();

    expect(WebhookEndpoint::find($id))->toBeNull();
});

test('an application activates a webhook only after a successful test delivery', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response('', 204)]);

    $id = test()->postJson('/api/v1/webhook-endpoints', [
        'url' => 'https://app.example.com/hooks/maacc',
    ])->assertCreated()->json('id');

    test()->postJson("/api/v1/webhook-endpoints/{$id}/verify")
        ->assertOk()
        ->assertJsonPath('verified', true)
        ->assertJsonPath('status', 'active');
});

test('deleting an unknown webhook endpoint returns a controlled error', function () {
    test()->deleteJson('/api/v1/webhook-endpoints/missing')
        ->assertStatus(404)
        ->assertJsonPath('error', 'webhook_endpoint_not_found');
});

test('a completed run delivers signed webhooks to subscribed endpoints', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response('', 200)]);

    $endpoint = webhookEndpoint(['events' => ['*']]);
    bindFakeRouter()->textThen('All good.');

    $response = test()->postJson('/api/v1/agents/ops-summary/runs', ['input' => 'Status?'])->assertCreated();
    $run = AgentRun::firstWhere('slug', $response->json('run_id'));

    $deliveries = WebhookDelivery::where('webhook_endpoint_id', $endpoint->id)->get();

    expect($deliveries)->toHaveCount(2)
        ->and($deliveries->pluck('event')->all())->toContain(WebhookEventType::RunRunning, WebhookEventType::RunCompleted)
        ->and($deliveries->every(fn (WebhookDelivery $d): bool => $d->status === WebhookDeliveryStatus::Delivered))->toBeTrue();

    Http::assertSent(function ($request) use ($endpoint) {
        $timestamp = $request->header('X-Maacc-Webhook-Timestamp')[0];
        $signature = $request->header('X-Maacc-Signature')[0];

        return WebhookSigner::verify($request->body(), $signature, $timestamp, $endpoint->secret, 300, (int) $timestamp);
    });
});

test('a failing webhook is retried then marked failed and observable', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response('boom', 500)]);

    $endpoint = webhookEndpoint(['events' => [WebhookEventType::RunCompleted->value]]);
    $run = AgentRun::factory()->for($this->agent)->for($this->application)->create([
        'environment' => Environment::Production,
        'status' => RunStatus::Completed,
    ]);

    $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->for($run)->create([
        'event' => WebhookEventType::RunCompleted,
        'payload' => ['event' => 'run.completed', 'run' => ['status' => 'completed']],
    ]);

    DeliverWebhook::dispatch($delivery);

    $delivery->refresh();

    expect($delivery->status)->toBe(WebhookDeliveryStatus::Failed)
        ->and($delivery->attempts)->toBe(config('maacc.runtime.webhooks.max_attempts'))
        ->and($delivery->response_status)->toBe(500)
        ->and($delivery->error)->toContain('500');

    expect($endpoint->fresh()->last_failed_at)->not->toBeNull();
});

test('a disabled endpoint short-circuits delivery', function () {
    $endpoint = webhookEndpoint(['status' => WebhookEndpointStatus::Disabled]);
    $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->create();

    DeliverWebhook::dispatch($delivery);

    expect($delivery->fresh()->status)->toBe(WebhookDeliveryStatus::Failed)
        ->and($delivery->fresh()->error)->toContain('disabled');
});

test('the webhook signature verifies only within the tolerance window', function () {
    $secret = 'whsec_test';
    $body = '{"event":"run.completed"}';
    $timestamp = '1000';
    $signature = WebhookSigner::header(WebhookSigner::sign($body, $timestamp, $secret));

    expect(WebhookSigner::verify($body, $signature, $timestamp, $secret, 300, 1100))->toBeTrue()
        ->and(WebhookSigner::verify($body, $signature, $timestamp, $secret, 300, 2000))->toBeFalse()
        ->and(WebhookSigner::verify($body, 'sha256=bad', $timestamp, $secret, 300, 1000))->toBeFalse()
        ->and(WebhookSigner::verify($body, $signature, 'not-a-number', $secret, 300, 1000))->toBeFalse();
});

test('registering a webhook without events subscribes to all events', function () {
    $response = test()->postJson('/api/v1/webhook-endpoints', [
        'url' => 'https://app.example.com/hooks/maacc',
    ])->assertStatus(201);

    expect($response->json('events'))->toBe(['*']);
});

test('a delivery records a connection failure', function () {
    Http::preventStrayRequests();
    Http::fake(fn () => throw new ConnectionException('Connection refused.'));

    $endpoint = webhookEndpoint();
    $run = AgentRun::factory()->for($this->agent)->for($this->application)->create(['environment' => Environment::Production]);
    $delivery = WebhookDelivery::factory()->for($endpoint, 'endpoint')->for($run)->create([
        'attempts' => (int) config('maacc.runtime.webhooks.max_attempts') - 1,
    ]);

    app()->call([new DeliverWebhook($delivery), 'handle']);

    expect($delivery->fresh()->status)->toBe(WebhookDeliveryStatus::Failed)
        ->and($delivery->fresh()->error)->toBe('The webhook endpoint could not be reached.');
});

test('delivery is a no-op when the delivery has been removed', function () {
    Http::preventStrayRequests();

    $delivery = WebhookDelivery::factory()->for(webhookEndpoint(), 'endpoint')->create();
    $delivery->delete();

    // fresh() resolves to null — the job returns without attempting a delivery.
    app()->call([new DeliverWebhook($delivery), 'handle']);

    expect(WebhookDelivery::count())->toBe(0);
});

test('an application exposes its registered webhook endpoints', function () {
    webhookEndpoint();
    webhookEndpoint();

    expect($this->application->webhookEndpoints()->count())->toBe(2);
});
