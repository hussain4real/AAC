<?php

namespace App\Actions\Maacc;

use App\Enums\LlmStatus;
use App\Models\LlmProvider;

class PublishLlmProvider
{
    /**
     * Publish a verified model to the live catalog by approving it. The caller
     * is responsible for ensuring a live verification has passed first.
     */
    public function handle(LlmProvider $llmProvider): LlmProvider
    {
        $llmProvider->update(['status' => LlmStatus::Approved]);

        return $llmProvider;
    }
}
