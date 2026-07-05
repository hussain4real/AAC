<?php

namespace App\Http\Resources\Maacc;

use App\Models\ModelRoutingPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes an advanced model routing policy for the console: the targeted
 * agent, the strategy, the ordered candidate chain (by model name), and the
 * cost/latency ceilings.
 *
 * @mixin ModelRoutingPolicy
 */
class ModelRoutingPolicyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->id,
            'id' => $this->id,
            'name' => $this->name,
            'agentId' => $this->agent_id,
            'agentName' => $this->whenLoaded('agent', fn () => $this->agent->name),
            'strategy' => $this->strategy->value,
            'strategyLabel' => $this->strategy->label(),
            'primaryProviderId' => $this->primary_provider_id,
            'primaryProvider' => $this->whenLoaded('primaryProvider', fn () => $this->primaryProvider?->name),
            'fallbackProviderIds' => $this->fallback_provider_ids ?? [],
            'maxCostPer1k' => $this->max_cost_per_1k,
            'maxLatencyMs' => $this->max_latency_ms,
            'enabled' => $this->enabled,
            'createdAt' => $this->created_at?->format('j M Y'),
        ];
    }
}
