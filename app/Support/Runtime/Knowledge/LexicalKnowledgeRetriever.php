<?php

namespace App\Support\Runtime\Knowledge;

use App\Models\KnowledgeChunk;
use App\Models\KnowledgeSource;
use App\Support\Runtime\Knowledge\Contracts\KnowledgeRetriever;

/**
 * The default, deterministic knowledge retriever. It scores each indexed chunk
 * by lexical overlap with the query — primarily how many distinct query terms
 * the chunk covers, tie-broken by term frequency then document order — so a
 * retrieval is reproducible and needs no external embedding service or model
 * spend. The relevance score returned is the query-term coverage (0–1).
 */
class LexicalKnowledgeRetriever implements KnowledgeRetriever
{
    /**
     * Score, rank, and return the most relevant chunks for the query.
     *
     * @return array<int, KnowledgeMatch>
     */
    public function retrieve(KnowledgeSource $source, string $query, int $topK, float $minScore): array
    {
        $terms = Tokenizer::terms($query);

        if ($terms === [] || $topK < 1) {
            return [];
        }

        $scored = [];

        /** @var KnowledgeChunk $chunk */
        foreach ($source->chunks()->with('document')->get() as $chunk) {
            $counts = array_count_values($chunk->tokens);
            $matched = 0;
            $frequency = 0;

            foreach ($terms as $term) {
                if (isset($counts[$term])) {
                    $matched++;
                    $frequency += $counts[$term];
                }
            }

            if ($matched === 0) {
                continue;
            }

            $coverage = round($matched / count($terms), 4);

            if ($coverage < $minScore) {
                continue;
            }

            $scored[] = compact('chunk', 'matched', 'frequency', 'coverage');
        }

        usort($scored, static function (array $a, array $b): int {
            return ([$b['matched'], $b['frequency']] <=> [$a['matched'], $a['frequency']])
                ?: ($a['chunk']->ordinal <=> $b['chunk']->ordinal)
                ?: strcmp($a['chunk']->id, $b['chunk']->id);
        });

        return array_map(
            fn (array $entry): KnowledgeMatch => $this->toMatch($entry),
            array_slice($scored, 0, $topK),
        );
    }

    /**
     * Build a match DTO from a scored chunk entry.
     *
     * @param  array{chunk: KnowledgeChunk, matched: int, frequency: int, coverage: float}  $entry
     */
    private function toMatch(array $entry): KnowledgeMatch
    {
        $chunk = $entry['chunk'];
        $document = $chunk->document;

        return new KnowledgeMatch(
            documentId: $chunk->knowledge_document_id,
            documentTitle: $document->title,
            uri: $document->uri,
            chunkId: $chunk->id,
            ordinal: $chunk->ordinal,
            content: $chunk->content,
            score: $entry['coverage'],
            termsMatched: $entry['matched'],
            indexedAt: $document->indexed_at?->toIso8601String(),
            metadata: $document->metadata ?? [],
        );
    }
}
