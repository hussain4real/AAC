<?php

namespace App\Models;

use Database\Factories\KnowledgeChunkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A retrievable chunk of a knowledge document produced by the indexing pipeline.
 * The retriever scores chunks against a query and maps the top matches back to
 * their document for citation. `tokens` is the normalized token list used by the
 * deterministic lexical retriever.
 *
 * @property string $id
 * @property string $knowledge_document_id
 * @property string $knowledge_source_id
 * @property int $ordinal
 * @property string $content
 * @property array<int, string> $tokens
 * @property int $token_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read KnowledgeDocument $document
 * @property-read KnowledgeSource $source
 */
#[Fillable(['knowledge_document_id', 'knowledge_source_id', 'ordinal', 'content', 'tokens', 'token_count'])]
class KnowledgeChunk extends Model
{
    /** @use HasFactory<KnowledgeChunkFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the document this chunk belongs to.
     *
     * @return BelongsTo<KnowledgeDocument, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(KnowledgeDocument::class, 'knowledge_document_id');
    }

    /**
     * Get the source this chunk belongs to.
     *
     * @return BelongsTo<KnowledgeSource, $this>
     */
    public function source(): BelongsTo
    {
        return $this->belongsTo(KnowledgeSource::class, 'knowledge_source_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tokens' => 'array',
            'ordinal' => 'integer',
            'token_count' => 'integer',
        ];
    }
}
