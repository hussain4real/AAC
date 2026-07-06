<?php

use App\Models\LlmProvider;
use App\Support\ProviderCatalog;

/**
 * The curated catalog powers the console picker/autocomplete, and code
 * normalisation strips a provider prefix the API does not expect — the guard
 * that stops a value like `openai/gpt-5.4` reaching the OpenAI API.
 */
test('the catalog exposes providers with bare-coded models', function () {
    $providers = ProviderCatalog::providers();

    expect($providers)->not->toBeEmpty();

    $openai = collect($providers)->firstWhere('driver', 'openai');

    expect($openai)->not->toBeNull()
        ->and($openai['label'])->toBe('OpenAI')
        ->and(collect($openai['models'])->pluck('code'))->toContain('gpt-5.4');

    // Every catalog code that is NOT an OpenRouter code must be free of a
    // provider prefix — the whole point of the curated list.
    foreach ($providers as $provider) {
        if ($provider['driver'] === 'openrouter') {
            continue;
        }

        foreach ($provider['models'] as $model) {
            expect($model['code'])->not->toContain('/');
            expect($model['input'])->toBeFloat();
            expect($model['output'])->toBeFloat();
        }
    }
});

test('normalizeCode strips a matching provider prefix', function () {
    expect(ProviderCatalog::normalizeCode('OpenAI', 'openai/gpt-5.4'))->toBe('gpt-5.4')
        ->and(ProviderCatalog::normalizeCode('OpenAI', 'gpt-5.4'))->toBe('gpt-5.4')
        ->and(ProviderCatalog::normalizeCode('OpenAI', '  openai/gpt-5.4  '))->toBe('gpt-5.4')
        ->and(ProviderCatalog::normalizeCode('Anthropic', 'anthropic/claude-opus-4-8'))->toBe('claude-opus-4-8')
        ->and(ProviderCatalog::normalizeCode('OpenAI', 'OpenAI/gpt-5.4'))->toBe('gpt-5.4');
});

test('normalizeCode preserves a code whose prefix is not the provider driver', function () {
    // OpenRouter legitimately addresses models as `vendor/model`, so the slash
    // must survive: its driver is `openrouter`, not `openai`.
    expect(ProviderCatalog::normalizeCode('OpenRouter', 'openai/gpt-5.4'))->toBe('openai/gpt-5.4')
        ->and(ProviderCatalog::normalizeCode('OpenAI', 'ft:gpt-4o:acme'))->toBe('ft:gpt-4o:acme');
});

test('driverFor maps provider labels to laravel/ai drivers', function () {
    expect(LlmProvider::driverFor('OpenAI'))->toBe('openai')
        ->and(LlmProvider::driverFor('Anthropic'))->toBe('anthropic')
        ->and(LlmProvider::driverFor('Google Gemini'))->toBe('gemini')
        ->and(LlmProvider::driverFor('Azure OpenAI'))->toBe('azure')
        ->and(LlmProvider::driverFor('AWS Bedrock'))->toBe('bedrock')
        ->and(LlmProvider::driverFor('Groq'))->toBe('groq')
        ->and(LlmProvider::driverFor('xAI'))->toBe('xai')
        ->and(LlmProvider::driverFor('DeepSeek'))->toBe('deepseek')
        ->and(LlmProvider::driverFor('Mistral'))->toBe('mistral')
        ->and(LlmProvider::driverFor('OpenRouter'))->toBe('openrouter');
});

test('driverFor falls back to the default provider when nothing matches', function () {
    config(['ai.default' => 'openai']);

    expect(LlmProvider::driverFor('Some Unlisted Vendor'))->toBe('openai');
});
