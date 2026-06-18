<?php

namespace App\Support\Runtime\Contracts;

use App\Support\Runtime\LlmCompletion;
use App\Support\Runtime\LlmRequest;

/**
 * Abstraction over the underlying LLM provider. The MAAC runtime drives the
 * orchestration loop itself (so it can pause for client-side tools), asking the
 * router for one turn at a time. Swapping the binding swaps the provider; tests
 * bind a deterministic fake so runs are reproducible without live API calls.
 */
interface LlmRouter
{
    /**
     * Produce the next conversation turn for the given request.
     */
    public function complete(LlmRequest $request): LlmCompletion;
}
