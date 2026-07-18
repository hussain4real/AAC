<?php

namespace App\Support\Webhooks;

use App\Enums\Environment;
use App\Enums\WebhookEndpointStatus;
use App\Enums\WebhookEventType;
use App\Jobs\DeliverWebhook;
use App\Models\Application;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;

/**
 * Fans a tool-implementation event out to every active webhook endpoint the
 * application has registered for it in the relevant environment, creating a
 * (run-less) {@see WebhookDelivery} record and queuing a
 * {@see DeliverWebhook} job for each. It is a no-op (one cheap query) when the
 * application has no matching endpoints, so reporting never burdens an
 * application that does not subscribe to implementation events.
 */
class ImplementationWebhookEmitter
{
    public function __construct(private readonly WebhookOutbox $outbox) {}

    /**
     * Emit an `implementation.reported` event when an application reports a
     * client-side tool handler.
     */
    public function emitReported(Application $application, Environment $environment, ToolContract $contract, ToolImplementation $implementation): void
    {
        $this->emit(WebhookEventType::ImplementationReported, $application, $environment, $contract, $implementation);
    }

    /**
     * Emit an `implementation.outdated` event when a contract change has
     * invalidated an application's previously-current handler.
     */
    public function emitOutdated(Application $application, Environment $environment, ToolContract $contract, ToolImplementation $implementation): void
    {
        $this->emit(WebhookEventType::ImplementationOutdated, $application, $environment, $contract, $implementation);
    }

    /**
     * Emit an `implementation.recovered` event when a previously-drifted handler
     * (outdated or incompatible) comes back into compatibility with its contract.
     */
    public function emitRecovered(Application $application, Environment $environment, ToolContract $contract, ToolImplementation $implementation): void
    {
        $this->emit(WebhookEventType::ImplementationRecovered, $application, $environment, $contract, $implementation);
    }

    /**
     * Fan an implementation event out to the application's subscribed endpoints.
     */
    private function emit(WebhookEventType $event, Application $application, Environment $environment, ToolContract $contract, ToolImplementation $implementation): void
    {
        $endpoints = WebhookEndpoint::query()
            ->where('application_id', $application->id)
            ->where('environment', $environment->value)
            ->where('status', WebhookEndpointStatus::Active)
            ->get()
            ->filter(fn (WebhookEndpoint $endpoint): bool => $endpoint->subscribesTo($event));

        if ($endpoints->isEmpty()) {
            return;
        }

        $payload = ImplementationWebhookPayload::for($event, $environment, $contract, $implementation);

        foreach ($endpoints as $endpoint) {
            $this->outbox->enqueue(
                $endpoint,
                $event,
                $payload,
                "implementation:{$implementation->id}:{$event->value}:{$contract->version}",
            );
        }
    }
}
