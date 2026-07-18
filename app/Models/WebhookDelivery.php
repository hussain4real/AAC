<?php

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use App\Enums\WebhookEventType;
use Database\Factories\WebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single attempt history record for delivering one run event to one webhook
 * endpoint. Persists the signed payload, the signature sent, the attempt count,
 * the last response/error, and the terminal state — the audit trail behind
 * "webhook failures are observable and retryable".
 *
 * @property string $id
 * @property string $webhook_endpoint_id
 * @property string|null $agent_run_id
 * @property WebhookEventType $event
 * @property array<string, mixed> $payload
 * @property string|null $signature
 * @property WebhookDeliveryStatus $status
 * @property int $attempts
 * @property int|null $response_status
 * @property string|null $response_body
 * @property string|null $error
 * @property Carbon|null $next_attempt_at
 * @property Carbon|null $last_attempted_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WebhookEndpoint $endpoint
 * @property-read AgentRun|null $agentRun
 */
#[Fillable(['webhook_endpoint_id', 'agent_run_id', 'event', 'event_sequence', 'deduplication_key', 'secret_version', 'payload', 'signature', 'status', 'attempts', 'processing_token', 'processing_claimed_at', 'replay_count', 'retained_until', 'response_status', 'response_body', 'error', 'next_attempt_at', 'last_attempted_at', 'delivered_at'])]
class WebhookDelivery extends Model
{
    /** @use HasFactory<WebhookDeliveryFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the endpoint the delivery targets.
     *
     * @return BelongsTo<WebhookEndpoint, $this>
     */
    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }

    /**
     * Get the run the delivery describes.
     *
     * @return BelongsTo<AgentRun, $this>
     */
    public function agentRun(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class);
    }

    /**
     * Whether the delivery can be replayed (re-dispatched).
     */
    public function isReplayable(): bool
    {
        return $this->status->isReplayable();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => WebhookEventType::class,
            'status' => WebhookDeliveryStatus::class,
            'payload' => 'array',
            'attempts' => 'integer',
            'event_sequence' => 'integer',
            'secret_version' => 'integer',
            'replay_count' => 'integer',
            'processing_claimed_at' => 'datetime',
            'retained_until' => 'datetime',
            'response_status' => 'integer',
            'next_attempt_at' => 'datetime',
            'last_attempted_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
