<?php

namespace App\Support\Runtime\Knowledge\Contracts;

use App\Models\KnowledgeSource;
use App\Support\Runtime\Knowledge\KnowledgeMatch;

/**
 * Retrieves the most relevant indexed chunks of a knowledge source for a query.
 *
 * The runtime depends on this abstraction (not a concrete implementation) so the
 * default deterministic lexical retriever can be swapped for an embedding-backed
 * one later without touching the executor — exactly as the LLM Router is bound.
 */
interface KnowledgeRetriever
{
    /**
     * Return up to {@see $topK} matches scoring at least {@see $minScore}
     * (query-term coverage, 0–1), most relevant first.
     *
     * @return array<int, KnowledgeMatch>
     */
    public function retrieve(KnowledgeSource $source, string $query, int $topK, float $minScore): array;
}
