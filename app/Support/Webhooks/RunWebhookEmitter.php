<?php

namespace App\Support\Webhooks;

use App\Enums\WebhookEndpointStatus;
use App\Enums\WebhookEventType;
use App\Jobs\DeliverWebhook;
use App\Models\AgentRun;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

/**
 * Fans a run lifecycle event out to every active webhook endpoint the run's
 * application has registered for that event in the run's environment, creating a
 * {@see WebhookDelivery} record and queuing a {@see DeliverWebhook}
 * job for each. The runtime calls this at each externally-observable transition;
 * it is a no-op (one cheap query) when the application has no matching endpoints,
 * so it never burdens runs that nobody subscribes to.
 */
class RunWebhookEmitter
{
    public function __construct(private readonly WebhookOutbox $outbox) {}

    /**
     * Emit a run event to all matching webhook endpoints.
     */
    public function emit(AgentRun $run, WebhookEventType $event): void
    {
        if ($run->environment === null) {
            return;
        }

        $endpoints = WebhookEndpoint::query()
            ->where('application_id', $run->application_id)
            ->where('environment', $run->environment->value)
            ->where('status', WebhookEndpointStatus::Active)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint): bool => $endpoint->subscribesTo($event));

        if ($endpoints->isEmpty()) {
            return;
        }

        $payload = WebhookPayload::for($run, $event);

        foreach ($endpoints as $endpoint) {
            $this->outbox->enqueue(
                $endpoint,
                $event,
                $payload,
                "run:{$run->id}:{$event->value}",
                $run,
            );
        }
    }
}
