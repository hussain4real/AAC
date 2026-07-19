<?php

namespace App\Support\Webhooks;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEventType;
use App\Jobs\DeliverWebhook;
use App\Models\AgentRun;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use Illuminate\Support\Facades\DB;

/** Atomic, deduplicated webhook outbox writer with endpoint-local ordering. */
class WebhookOutbox
{
    /** @param array<string, mixed> $payload */
    public function enqueue(
        WebhookEndpoint $endpoint,
        WebhookEventType $event,
        array $payload,
        string $deduplicationKey,
        ?AgentRun $run = null,
    ): WebhookDelivery {
        [$delivery, $created] = DB::transaction(function () use ($endpoint, $event, $payload, $deduplicationKey, $run): array {
            $locked = WebhookEndpoint::query()->lockForUpdate()->findOrFail($endpoint->id);
            $existing = $locked->deliveries()->where('deduplication_key', $deduplicationKey)->first();

            if ($existing instanceof WebhookDelivery) {
                return [$existing, false];
            }

            $delivery = $locked->deliveries()->create([
                'agent_run_id' => $run?->id,
                'event' => $event,
                'event_sequence' => $locked->next_delivery_sequence,
                'deduplication_key' => $deduplicationKey,
                'secret_version' => $locked->secret_version,
                'payload' => $payload,
                'status' => WebhookDeliveryStatus::Pending,
                'attempts' => 0,
                'retained_until' => now()->addDays((int) config('maacc.runtime.webhooks.retention_days', 30)),
            ]);

            $locked->update(['next_delivery_sequence' => $locked->next_delivery_sequence + 1]);

            return [$delivery, true];
        });

        if ($created) {
            DeliverWebhook::dispatch($delivery)->onQueue('webhooks')->afterCommit();
        }

        return $delivery;
    }
}
