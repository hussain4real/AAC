<?php

namespace App\Http\Resources\Maac;

use App\Models\AgentRun;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes an AgentRun to the Phase 1 console contract shape
 * (resources/js/maac/data.ts `Run`). `status` keeps its raw enum value.
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
            'latency' => $this->latency_ms !== null ? number_format($this->latency_ms / 1000, 1).'s' : '—',
            'started' => $this->started_at?->format('d M H:i:s') ?? '—',
            'completed' => $this->completed_at?->format('d M H:i:s') ?? '—',
            'input' => $this->input,
            'error' => $this->when($this->error !== null, $this->error),
            'failureReason' => $this->when($this->failure_reason !== null, $this->failure_reason),
        ];
    }
}
