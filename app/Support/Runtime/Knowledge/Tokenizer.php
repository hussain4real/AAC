<?php

namespace App\Support\Runtime\Knowledge;

use Illuminate\Support\Str;

/**
 * Normalizes text into a deterministic list of lexical tokens for the
 * {@see LexicalKnowledgeRetriever}: lowercased, split on non-alphanumeric
 * boundaries, with very short tokens and a small stopword set removed.
 * Repeats are preserved so the retriever can weight by term frequency.
 */
class Tokenizer
{
    /**
     * A small English stopword set dropped from both documents and queries so
     * common words do not dominate the lexical overlap score.
     *
     * @var array<int, string>
     */
    private const STOPWORDS = [
        'the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'is', 'are',
        'was', 'were', 'be', 'been', 'by', 'with', 'at', 'as', 'it', 'its', 'this',
        'that', 'these', 'those', 'from', 'into', 'than', 'then', 'but', 'not',
        'we', 'our', 'you', 'your', 'they', 'their', 'i', 'me', 'my',
    ];

    /**
     * Tokenize the given text into normalized lexical tokens (repeats kept).
     *
     * @return array<int, string>
     */
    public static function tokenize(string $text): array
    {
        $normalized = Str::lower($text);
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $normalized) ?: [];

        $tokens = [];

        foreach ($parts as $part) {
            if (Str::length($part) < 2) {
                continue;
            }

            if (in_array($part, self::STOPWORDS, true)) {
                continue;
            }

            $tokens[] = $part;
        }

        return $tokens;
    }

    /**
     * Tokenize and reduce to the distinct query terms (order preserved).
     *
     * @return array<int, string>
     */
    public static function terms(string $text): array
    {
        return array_values(array_unique(self::tokenize($text)));
    }
}
