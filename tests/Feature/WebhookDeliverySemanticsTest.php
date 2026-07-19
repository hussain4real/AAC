<?php

use App\Enums\Environment;
use App\Enums\WebhookEndpointStatus;
use App\Enums\WebhookEventType;
use App\Jobs\DeliverWebhook;
use App\Models\AgentRun;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\Webhooks\WebhookOutbox;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    [, $this->team] = ownerAndTeam();
    $this->agent = maaccAgent($this->team);
    $this->application = $this->agent->project->application;
    $this->endpoint = WebhookEndpoint::factory()->for($this->application)->create([
        'status' => WebhookEndpointStatus::Active,
        'environment' => Environment::Production,
        'events' => ['*'],
    ]);
    $this->run = AgentRun::factory()->for($this->agent)->for($this->application)->create([
        'environment' => Environment::Production,
    ]);
});

test('the webhook outbox deduplicates events and assigns stable endpoint ordering', function () {
    Queue::fake();
    $outbox = app(WebhookOutbox::class);

    $first = $outbox->enqueue($this->endpoint, WebhookEventType::RunRunning, ['event' => 'running'], 'run:1:running', $this->run);
    $duplicate = $outbox->enqueue($this->endpoint, WebhookEventType::RunRunning, ['event' => 'running'], 'run:1:running', $this->run);
    $second = $outbox->enqueue($this->endpoint, WebhookEventType::RunCompleted, ['event' => 'completed'], 'run:1:completed', $this->run);

    expect($duplicate->is($first))->toBeTrue()
        ->and($first->event_sequence)->toBe(1)
        ->and($second->event_sequence)->toBe(2)
        ->and(WebhookDelivery::count())->toBe(2);

    Queue::assertPushed(DeliverWebhook::class, 2);
});

test('delivery exposes idempotency ordering replay and key-version headers', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response('', 204)]);
    config(['maacc.outbound.webhook.allowed_hosts' => ['app.example.com']]);

    $delivery = app(WebhookOutbox::class)->enqueue(
        $this->endpoint,
        WebhookEventType::RunCompleted,
        ['event' => 'completed'],
        'run:1:completed',
        $this->run,
    );

    expect($delivery->fresh()->status->value)->toBe('delivered');

    Http::assertSent(fn ($request): bool => $request->hasHeader('X-Maacc-Webhook-Delivery', $delivery->id)
        && $request->hasHeader('X-Maacc-Webhook-Sequence', '1')
        && $request->hasHeader('X-Maacc-Webhook-Replay', '0')
        && $request->hasHeader('X-Maacc-Webhook-Key-Version', '1'));
});

test('secret rotation retains only a bounded verification overlap', function () {
    $original = $this->endpoint->secret;
    $this->endpoint->rotateSecret(WebhookEndpoint::generateSecret());
    $this->endpoint->save();

    expect($this->endpoint->signingSecretFor(1))->toBe($original)
        ->and($this->endpoint->signingSecretFor(2))->toBe($this->endpoint->secret);

    $this->travel((int) config('maacc.runtime.webhooks.secret_rotation_overlap_seconds') + 1)->seconds();

    expect($this->endpoint->signingSecretFor(1))->toBeNull();
});

test('a duplicate queued job cannot redeliver an acknowledged delivery', function () {
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response('', 204)]);
    config(['maacc.outbound.webhook.allowed_hosts' => ['app.example.com']]);
    Queue::fake();

    $delivery = app(WebhookOutbox::class)->enqueue(
        $this->endpoint,
        WebhookEventType::RunCompleted,
        ['event' => 'completed'],
        'run:1:completed',
        $this->run,
    );

    app()->call([new DeliverWebhook($delivery), 'handle']);
    app()->call([new DeliverWebhook($delivery), 'handle']);

    Http::assertSentCount(1);
    expect($delivery->fresh()->attempts)->toBe(1);
});
