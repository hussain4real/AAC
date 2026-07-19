<?php

namespace App\Http\Resources\Maacc;

use App\Models\AgentRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes an AgentRun to the Phase 1 console contract shape
 * (resources/js/maacc/data.ts `Run`). `status` keeps its raw enum value.
 *
 * @mixin AgentRun
 */
class AgentRunResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'agentId' => $this->whenLoaded('agent', fn () => $this->agent->slug),
            'appId' => $this->whenLoaded('application', fn () => $this->application->slug),
            'projectId' => $this->whenLoaded('project', fn () => $this->project->slug),
            'caller' => $this->caller,
            'status' => $this->status->value,
            'sensitivity' => $this->sensitivity->label(),
            'masked' => $this->masked,
            'llm' => $this->whenLoaded('llmProvider', fn () => $this->llmProvider?->slug),
            'tools' => $this->tools ?? [],
            'tokensIn' => $this->tokens_in,
            'tokensOut' => $this->tokens_out,
            'cost' => $this->cost,
            'currency' => $this->cost_currency,
            'pricing' => [
                'source' => $this->pricing_source,
                'version' => $this->pricing_version,
                'effectiveAt' => $this->pricing_effective_at?->toIso8601String(),
                'estimated' => true,
            ],
            'latency' => $this->latency_ms !== null ? number_format($this->latency_ms / 1000, 1).'s' : '—',
            'latencyMs' => $this->latency_ms,
            'started' => $this->started_at?->format('d M H:i:s') ?? '—',
            'completed' => $this->completed_at?->format('d M H:i:s') ?? '—',
            'startedAt' => $this->started_at?->toIso8601String(),
            'completedAt' => $this->completed_at?->toIso8601String(),
            'input' => $this->input,
            'output' => $this->output,
            'error' => $this->when($this->error !== null, $this->error),
            'failureReason' => $this->when($this->failure_reason !== null, $this->failure_reason),
        ];
    }
}
