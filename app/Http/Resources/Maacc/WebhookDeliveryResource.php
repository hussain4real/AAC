<?php

namespace App\Http\Resources\Maacc;

use App\Models\WebhookDelivery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a webhook delivery attempt for the console observability feed:
 * which event, its outcome, the attempt count, and the last response/error.
 *
 * @mixin WebhookDelivery
 */
class WebhookDeliveryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event' => $this->event->value,
            'eventLabel' => $this->event->label(),
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'attempts' => $this->attempts,
            'responseStatus' => $this->response_status,
            'error' => $this->error,
            'runId' => $this->whenLoaded('agentRun', fn () => $this->agentRun?->slug),
            'lastAttemptedAt' => $this->last_attempted_at?->diffForHumans(),
            'deliveredAt' => $this->delivered_at?->diffForHumans(),
            'createdAt' => $this->created_at?->diffForHumans(),
            'replayable' => $this->isReplayable(),
        ];
    }
}
