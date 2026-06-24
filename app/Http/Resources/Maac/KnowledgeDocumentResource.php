<?php

namespace App\Http\Resources\Maac;

use App\Models\KnowledgeDocument;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes an ingested knowledge document for the console: its attribution
 * (title + uri), freshness, indexed chunk count, metadata, and — when the
 * document was uploaded rather than pasted — its original filename and size.
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
            'uploaded' => $this->isUploaded(),
            'originalFilename' => $this->original_filename,
            'fileSize' => $this->file_size,
            'createdAt' => $this->created_at?->format('j M Y'),
        ];
    }
}
