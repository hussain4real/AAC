<?php

namespace App\Http\Resources\Maac;

use App\Models\Agent;
use App\Support\Runtime\AgentPromptComposer;
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
            'uuid' => $this->id,
            'id' => $this->slug,
            'name' => $this->name,
            'projectId' => $this->whenLoaded('project', fn () => $this->project->slug),
            'appId' => $this->whenLoaded('project', fn () => $this->project->application->slug),
            'llm' => $this->whenLoaded('llmProvider', fn () => $this->llmProvider->slug),
            'version' => $this->version,
            'status' => $this->status->label(),
            'successRate' => $this->success_rate,
            'lastRun' => $this->last_run_at?->diffForHumans() ?? '—',
            'runs7d' => $this->runs_7d,
            'desc' => $this->description,
            'tools' => $this->whenLoaded('tools', fn () => $this->tools->pluck('slug')->all()),
            'slug' => $this->agent_slug,
            'temp' => $this->temperature,
            'maxTokens' => $this->max_tokens,
            'prompt' => $this->system_prompt,
            'effectivePrompt' => $this->whenLoaded(
                'tools',
                fn (): string => (new AgentPromptComposer)->compose($this->resource),
                $this->system_prompt,
            ),
        ];
    }
}
