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
 * @property string|null $disk
 * @property string|null $storage_path
 * @property string|null $original_filename
 * @property string|null $mime_type
 * @property int|null $file_size
 * @property array<string, mixed>|null $metadata
 * @property Carbon|null $indexed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read KnowledgeSource $source
 * @property-read Collection<int, KnowledgeChunk> $chunks
 */
#[Fillable(['knowledge_source_id', 'title', 'uri', 'body', 'checksum', 'disk', 'storage_path', 'original_filename', 'mime_type', 'file_size', 'metadata', 'indexed_at'])]
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
     * Determine whether this document was ingested from an uploaded file (as
     * opposed to a pasted body). Uploaded documents keep their source file in
     * storage so re-indexing can re-read and re-extract them.
     */
    public function isUploaded(): bool
    {
        return $this->storage_path !== null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'metadata' => 'array',
            'indexed_at' => 'datetime',
        ];
    }
}
