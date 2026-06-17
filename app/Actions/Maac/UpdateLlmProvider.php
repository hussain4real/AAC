<?php

namespace App\Actions\Maac;

use App\Models\LlmProvider;

class UpdateLlmProvider
{
    /**
     * Update a model in the approved LLM catalog.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(LlmProvider $llmProvider, array $data): LlmProvider
    {
        $llmProvider->update($data);

        return $llmProvider;
    }
}
