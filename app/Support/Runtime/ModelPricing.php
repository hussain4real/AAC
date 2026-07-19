<?php

namespace App\Support\Runtime;

use App\Models\LlmProvider;

/**
 * Estimates the source-currency cost of model usage. There is no authoritative source
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
        $quote = $this->quoteFor($provider);

        return ['input' => $quote['input'], 'output' => $quote['output']];
    }

    /**
     * Resolve the governed rate quote and its reporting provenance.
     *
     * @return array{input: float, output: float, currency: string, unit: string, source: string, version: string, effectiveAt: string|null}
     */
    public function quoteFor(LlmProvider $provider): array
    {
        $catalog = config('maacc.pricing.models');
        $entry = is_array($catalog) ? ($catalog[$provider->code] ?? null) : null;

        if (is_array($entry)) {
            return [
                'input' => (float) $entry['input'],
                'output' => (float) $entry['output'],
                'currency' => (string) config('maacc.pricing.currency', 'USD'),
                'unit' => (string) config('maacc.pricing.unit', 'per_million_tokens'),
                'source' => (string) config('maacc.pricing.source', 'MAACC governed catalog'),
                'version' => (string) config('maacc.pricing.version', 'unversioned'),
                'effectiveAt' => config('maacc.pricing.effective_at'),
            ];
        }

        return [
            'input' => $provider->input_cost,
            'output' => $provider->output_cost,
            'currency' => $provider->pricing_currency,
            'unit' => $provider->pricing_unit,
            'source' => $provider->pricing_source,
            'version' => $provider->pricing_version,
            'effectiveAt' => $provider->pricing_effective_at?->toIso8601String(),
        ];
    }

    /**
     * Estimate the source-currency cost of a turn from its token usage.
     */
    public function estimate(LlmProvider $provider, int $tokensIn, int $tokensOut): float
    {
        $rates = $this->ratesFor($provider);

        return $tokensIn / 1_000_000 * $rates['input']
            + $tokensOut / 1_000_000 * $rates['output'];
    }
}
