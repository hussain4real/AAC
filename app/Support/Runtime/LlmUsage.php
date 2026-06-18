<?php

namespace App\Support\Runtime;

/**
 * Token usage reported by the LLM Router for a single completion.
 */
final readonly class LlmUsage
{
    public function __construct(
        public int $tokensIn = 0,
        public int $tokensOut = 0,
    ) {}
}
