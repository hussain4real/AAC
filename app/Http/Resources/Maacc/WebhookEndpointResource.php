<?php

namespace App\Http\Resources\Maacc;

use App\Models\WebhookEndpoint;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a webhook endpoint and its recent delivery history for the console.
 * The signing secret is never exposed; only its last characters are shown.
 *
 * @mixin WebhookEndpoint
 */
class WebhookEndpointResource extends JsonResource
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
            'uuid' => $this->id,
            'appId' => $this->whenLoaded('application', fn () => $this->application->slug),
            'appName' => $this->whenLoaded('application', fn () => $this->application->name),
            'environment' => $this->environment->label(),
            'url' => $this->url,
            'events' => $this->events,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'description' => $this->description,
            'lastFour' => $this->last_four,
            'lastDeliveredAt' => $this->last_delivered_at?->diffForHumans(),
            'lastFailedAt' => $this->last_failed_at?->diffForHumans(),
            'createdAt' => $this->created_at?->format('j M Y'),
            'deliveries' => $this->whenLoaded('deliveries', fn () => WebhookDeliveryResource::collection($this->deliveries)->resolve()),
        ];
    }
}
