<?php

namespace App\Support\Runtime;

use App\Models\LlmProvider;

/**
 * Estimates the US-dollar cost of model usage. There is no authoritative source
 * of per-request cost — providers return token *usage* through their API, never
 * a dollar amount — so cost is always usage multiplied by a maintained price
 * table. The reviewed `maacc.pricing` catalog (per 1,000,000 tokens) is the
 * source of truth for known model codes; an unknown model falls back to the
 * per-1M rates stored on its catalog row, so custom/on-prem models still price.
 */
class ModelPricing
{
    /**
     * Resolve the per-1,000,000-token input/output rates for a model.
     *
     * @return array{input: float, output: float}
     */
    public function ratesFor(LlmProvider $provider): array
    {
        $catalog = config('maacc.pricing.models');
        $entry = is_array($catalog) ? ($catalog[$provider->code] ?? null) : null;

        if (is_array($entry)) {
            return ['input' => (float) $entry['input'], 'output' => (float) $entry['output']];
        }

        return ['input' => $provider->input_cost, 'output' => $provider->output_cost];
    }

    /**
     * Estimate the dollar cost of a turn from its token usage.
     */
    public function estimate(LlmProvider $provider, int $tokensIn, int $tokensOut): float
    {
        $rates = $this->ratesFor($provider);

        return $tokensIn / 1_000_000 * $rates['input']
            + $tokensOut / 1_000_000 * $rates['output'];
    }
}
