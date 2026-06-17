<?php

namespace App\Actions\Maac;

use App\Models\LlmProvider;

class DeleteLlmProvider
{
    /**
     * Delete a model from the approved LLM catalog.
     */
    public function handle(LlmProvider $llmProvider): void
    {
        $llmProvider->delete();
    }
}
