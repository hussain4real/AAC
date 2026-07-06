<?php

namespace App\Support;

use App\Models\LlmProvider;
use Illuminate\Support\Str;

/**
 * Reads the curated provider/model catalog (config/maacc-catalog.php) that backs
 * the console's provider picker and model autocomplete, and normalises a model
 * code the way the provider's API expects it.
 */
final class ProviderCatalog
{
    /**
     * The catalog of providers and their known models, shaped for the console.
     *
     * @return list<array{driver: string, label: string, models: list<array{code: string, label: string, context: string, input: float, output: float}>}>
     */
    public static function providers(): array
    {
        /** @var list<array{driver: string, label: string, models: list<array<string, mixed>>}> $providers */
        $providers = config('maacc-catalog.providers', []);

        return array_map(static fn (array $provider): array => [
            'driver' => (string) $provider['driver'],
            'label' => (string) $provider['label'],
            'models' => array_map(static fn (array $model): array => [
                'code' => (string) $model['code'],
                'label' => (string) $model['label'],
                'context' => (string) $model['context'],
                'input' => (float) $model['input'],
                'output' => (float) $model['output'],
            ], $provider['models']),
        ], $providers);
    }

    /**
     * Normalise a model code for the given provider label by stripping a leading
     * `{driver}/` prefix that the provider's API does not expect — e.g.
     * `openai/gpt-5.4` becomes `gpt-5.4` for the OpenAI provider. A prefix that
     * does not match the provider's own driver (such as an OpenRouter
     * `openai/…` code) is preserved, because there the slash is meaningful.
     */
    public static function normalizeCode(string $providerLabel, string $code): string
    {
        $code = trim($code);
        $prefix = LlmProvider::driverFor($providerLabel).'/';

        if (Str::startsWith(Str::lower($code), Str::lower($prefix))) {
            return substr($code, strlen($prefix));
        }

        return $code;
    }
}
