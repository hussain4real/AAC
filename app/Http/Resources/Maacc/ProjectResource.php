<?php

namespace App\Http\Resources\Maacc;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a Project to the Phase 1 console contract shape
 * (resources/js/maacc/data.ts `Project`).
 *
 * @mixin Project
 */
class ProjectResource extends JsonResource
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
            'appId' => $this->whenLoaded('application', fn () => $this->application->slug),
            'env' => $this->environment->label(),
            'desc' => $this->description,
            'bizOwner' => $this->business_owner,
            'techOwner' => $this->technical_owner,
            'status' => $this->status->label(),
            'llms' => $this->whenLoaded('llmProviders', fn () => $this->llmProviders->pluck('slug')->all()),
            'agents' => $this->agents_count,
            'tools' => $this->tools_count,
            'runs7d' => $this->runs_7d,
            'members' => $this->whenLoaded('projectMembers', fn () => ProjectMemberResource::collection($this->projectMembers)->resolve(), []),
            'can' => [
                'manageMembers' => $request->user()?->can('manageMembers', $this->resource) ?? false,
            ],
        ];
    }
}
