<?php

namespace App\Http\Resources\Maac;

use App\Models\EvaluationDataset;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a golden evaluation dataset for the console, with its cases when
 * loaded.
 *
 * @mixin EvaluationDataset
 */
class EvaluationDatasetResource extends JsonResource
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
            'description' => $this->description,
            'projectId' => $this->project_id,
            'project' => $this->whenLoaded('project', fn () => $this->project?->name),
            'caseCount' => $this->whenCounted('cases'),
            'cases' => $this->whenLoaded('cases', fn () => EvaluationCaseResource::collection($this->cases)->resolve(), []),
            'createdAt' => $this->created_at?->format('j M Y'),
        ];
    }
}
