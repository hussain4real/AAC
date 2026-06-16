<?php

namespace App\Http\Resources\Maac;

use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a Project to the Phase 1 console contract shape
 * (resources/js/maac/data.ts `Project`).
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
            'id' => $this->slug,
            'name' => $this->name,
            'appId' => $this->application->slug,
            'env' => $this->environment->label(),
            'desc' => $this->description,
            'bizOwner' => $this->business_owner,
            'techOwner' => $this->technical_owner,
            'status' => $this->status->label(),
            'llms' => $this->llmProviders->pluck('slug')->all(),
            'agents' => $this->agents_count,
            'tools' => $this->tools_count,
            'runs7d' => $this->runs_7d,
        ];
    }
}
