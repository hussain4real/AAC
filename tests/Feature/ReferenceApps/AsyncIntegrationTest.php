<?php

use App\Actions\Maacc\CreateCredential;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\User;
use Database\Seeders\MaaccE2ESeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Maacc\Reference\Cli\CliConsumer;
use Maacc\Reference\Cli\FetchRecordsHandler;
use Maacc\Reference\Cli\WebhookReceiver;
use Maacc\Sdk\MaaccClient;
use Maacc\Sdk\MaaccConfig;
use Maacc\Sdk\Tools\ToolHandlerRegistry;
use Maacc\Sdk\Webhooks\WebhookSignature;
use Tests\Support\Sdk\KernelTransport;

/**
 * Phase 6D: an external reference application drives the long-running and
 * interactive runtime modes — asynchronous polling, signed webhook delivery, and
 * streaming — through the public SDK only, against a seeded MAACC instance over
 * the in-process kernel transport.
 */
beforeEach(function () {
    if (! file_exists(storage_path('oauth-private.key'))) {
        Artisan::call('passport:keys');
    }

    $this->seed(MaaccE2ESeeder::class);
    $this->application = Application::firstWhere('slug', MaaccE2ESeeder::APP_SLUG);
    $owner = User::firstWhere('email', MaaccE2ESeeder::USER_EMAIL);

    $issued = app(CreateCredential::class)->handle($this->application, $owner, ['environment' => 'production']);
    $this->plainSecret = $issued->plainSecret;
    $this->client = new MaaccClient(
        new MaaccConfig('', $issued->credential->client_id, $this->plainSecret),
        new KernelTransport($this),
    );
});

it('drives a long-running async run through polling and a client tool', function () {
    $registry = (new ToolHandlerRegistry)->register(new FetchRecordsHandler(MaaccE2ESeeder::TOOL_SLUG));
    $consumer = new CliConsumer($this->client, $registry, MaaccE2ESeeder::AGENT_SLUG);

    bindFakeRouter()
        ->toolCallThen(MaaccE2ESeeder::TOOL_SLUG, ['query' => 'today'])
        ->textThen('All berths are clear.');

    $run = $consumer->runAsync('Summarize today', 'cli-async', ['intervalMs' => 0]);

    expect($run->isCompleted())->toBeTrue()
        ->and($run->response)->toBe('All berths are clear.');

    // The async run records the same trace, mode, and cost data as a sync run.
    $record = AgentRun::firstWhere('slug', $run->runId);
    expect($record->mode->value)->toBe('async')
        ->and($record->cost)->toBeGreaterThan(0)
        ->and($record->traceEvents()->count())->toBeGreaterThan(0);
});

it('receives a signed webhook the receiver verifies, for a completed run', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response('', 200)]);

    $endpoint = $this->client->registerWebhook('https://consumer.test/hooks/maacc', ['*']);
    $receiver = new WebhookReceiver($endpoint->secret);
    $verified = $this->client->verifyWebhook($endpoint->id);

    expect($verified->status)->toBe('active');

    bindFakeRouter()->textThen('Done.');
    $this->client->run(MaaccE2ESeeder::AGENT_SLUG, 'Status?', new ToolHandlerRegistry, 'cli-webhook');

    Http::assertSent(function ($request) use ($receiver): bool {
        if (($request->header('X-Maacc-Webhook-Event')[0] ?? '') !== 'run.completed') {
            return false;
        }

        $verified = $receiver->handle($request->body(), [
            'X-Maacc-Signature' => $request->header('X-Maacc-Signature')[0] ?? '',
            'X-Maacc-Webhook-Timestamp' => $request->header('X-Maacc-Webhook-Timestamp')[0] ?? '',
            'X-Maacc-Webhook-Delivery' => $request->header('X-Maacc-Webhook-Delivery')[0] ?? '',
            'X-Maacc-Webhook-Sequence' => $request->header('X-Maacc-Webhook-Sequence')[0] ?? '',
        ]);

        return $verified !== null && $verified['event'] === 'run.completed';
    });
});

it('acknowledges a duplicate delivery id without treating it as new work', function () {
    $secret = 'whsec_consumer_test';
    $body = '{"event":"run.completed"}';
    $timestamp = (string) now()->timestamp;
    $receiver = new WebhookReceiver($secret);
    $headers = [
        'X-Maacc-Signature' => 'sha256='.WebhookSignature::sign($body, $timestamp, $secret),
        'X-Maacc-Webhook-Timestamp' => $timestamp,
        'X-Maacc-Webhook-Delivery' => 'delivery-1',
        'X-Maacc-Webhook-Sequence' => '42',
    ];

    expect($receiver->handle($body, $headers))->not->toBeNull()
        ->and($receiver->wasDuplicate())->toBeFalse()
        ->and($receiver->sequence())->toBe(42)
        ->and($receiver->handle($body, $headers))->not->toBeNull()
        ->and($receiver->wasDuplicate())->toBeTrue();
});

it('streams a run and sees the same final state as the polling API', function () {
    bindFakeRouter()->textThen('Streamed answer.');
    $run = $this->client->run(MaaccE2ESeeder::AGENT_SLUG, 'Status?', new ToolHandlerRegistry, 'cli-stream');

    $events = $this->client->streamRun($run->runId);

    $traceEvents = array_values(array_filter($events, fn ($event): bool => $event->event === 'run.event'));
    $stateEvents = array_values(array_filter($events, fn ($event): bool => $event->event === 'run.state'));

    expect($traceEvents)->not->toBeEmpty()
        ->and($stateEvents)->not->toBeEmpty();

    $finalState = end($stateEvents);
    expect($finalState->data['status'])->toBe('completed')
        ->and($finalState->data['response'])->toBe('Streamed answer.');
});
