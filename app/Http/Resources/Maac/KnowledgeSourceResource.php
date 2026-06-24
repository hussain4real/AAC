<?php

namespace App\Http\Resources\Maac;

use App\Models\KnowledgeSource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a governed knowledge (RAG) source for the console: its lifecycle
 * status, sensitivity, the environments it serves, freshness metadata, and —
 * when loaded — its ingested documents.
 *
 * @mixin KnowledgeSource
 */
class KnowledgeSourceResource extends JsonResource
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
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'sensitivity' => $this->sensitivity->label(),
            'requiresApproval' => $this->requires_approval,
            'environments' => array_map(ucfirst(...), $this->environments),
            'documentCount' => $this->document_count,
            'chunkCount' => $this->chunk_count,
            'toolCount' => $this->whenCounted('tools'),
            'lastIndexed' => $this->last_indexed_at?->diffForHumans(),
            'owner' => $this->application_id === null ? 'Platform' : $this->whenLoaded('application', fn () => $this->application?->name),
            'documents' => $this->whenLoaded('documents', fn () => KnowledgeDocumentResource::collection($this->documents)->resolve(), []),
            'createdAt' => $this->created_at?->format('j M Y'),
        ];
    }
}
