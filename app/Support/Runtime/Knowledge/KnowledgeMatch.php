<?php

namespace App\Support\Runtime\Knowledge;

/**
 * A single retrieved chunk and the metadata needed to build a citation: the
 * source document's title/uri, the chunk's position and content, the relevance
 * score (query-term coverage, 0–1), how many query terms it matched, and the
 * document's freshness/attribution metadata.
 */
class KnowledgeMatch
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public readonly string $documentId,
        public readonly string $documentTitle,
        public readonly ?string $uri,
        public readonly string $chunkId,
        public readonly int $ordinal,
        public readonly string $content,
        public readonly float $score,
        public readonly int $termsMatched,
        public readonly ?string $indexedAt,
        public readonly array $metadata = [],
    ) {}

    /**
     * The retrieved passage shape returned to the model.
     *
     * @return array<string, mixed>
     */
    public function toMatch(): array
    {
        return [
            'content' => $this->content,
            'score' => $this->score,
            'source' => $this->documentTitle,
        ];
    }

    /**
     * The citation shape (attribution + freshness) returned alongside the match.
     *
     * @return array<string, mixed>
     */
    public function toCitation(): array
    {
        return [
            'document' => $this->documentTitle,
            'uri' => $this->uri,
            'chunk' => $this->ordinal,
            'score' => $this->score,
            'indexed_at' => $this->indexedAt,
        ];
    }
}
