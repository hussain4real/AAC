<?php

namespace App\Http\Resources\Maac;

use App\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes an Agent to the Phase 1 console contract shape
 * (resources/js/maac/data.ts `Agent`). Note `slug` carries the runtime
 * `agent_slug` while `id` carries the stable fixture identifier.
 *
 * @mixin Agent
 */
class AgentResource extends JsonResource
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
            'name' => $this->name,
            'projectId' => $this->project->slug,
            'appId' => $this->project->application->slug,
            'llm' => $this->llmProvider->slug,
            'version' => $this->version,
            'status' => $this->status->label(),
            'successRate' => $this->success_rate,
            'lastRun' => $this->last_run_at?->diffForHumans() ?? '—',
            'runs7d' => $this->runs_7d,
            'desc' => $this->description,
            'tools' => $this->tools->pluck('slug')->all(),
            'slug' => $this->agent_slug,
            'temp' => $this->temperature,
            'maxTokens' => $this->max_tokens,
            'prompt' => $this->system_prompt,
        ];
    }
}
