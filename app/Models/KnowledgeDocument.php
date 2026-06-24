<?php

namespace App\Models;

use Database\Factories\KnowledgeDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A single ingested document within a knowledge source. It carries the source
 * attribution (title + uri) and freshness metadata used to build citations, the
 * raw body the indexer chunks, and a checksum used to detect content changes on
 * re-ingest.
 *
 * @property string $id
 * @property string $knowledge_source_id
 * @property string $title
 * @property string|null $uri
 * @property string $body
 * @property string $checksum
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $indexed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read KnowledgeSource $source
 * @property-read Collection<int, KnowledgeChunk> $chunks
 */
#[Fillable(['knowledge_source_id', 'title', 'uri', 'body', 'checksum', 'metadata', 'indexed_at'])]
class KnowledgeDocument extends Model
{
    /** @use HasFactory<KnowledgeDocumentFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the source this document belongs to.
     *
     * @return BelongsTo<KnowledgeSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    /**
     * Get the indexed chunks produced from this document.
     *
     * @return HasMany<KnowledgeChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(KnowledgeChunk::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'indexed_at' => 'datetime',
        ];
    }
}
