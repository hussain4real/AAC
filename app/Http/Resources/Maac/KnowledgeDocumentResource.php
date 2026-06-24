<?php

namespace App\Http\Resources\Maac;

use App\Models\KnowledgeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes an ingested knowledge document for the console: its attribution
 * (title + uri), freshness, indexed chunk count, and metadata.
 *
 * @mixin KnowledgeDocument
 */
class KnowledgeDocumentResource extends JsonResource
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
            'title' => $this->title,
            'uri' => $this->uri,
            'chunkCount' => $this->whenCounted('chunks'),
            'indexedAt' => $this->indexed_at?->diffForHumans(),
            'metadata' => $this->metadata ?? [],
            'createdAt' => $this->created_at?->format('j M Y'),
        ];
    }
}
