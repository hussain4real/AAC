<?php

use App\Enums\LlmVerificationOutcome;
use App\Enums\VaultSecretKind;
use App\Models\LlmProvider;
use App\Support\Runtime\Contracts\LlmRouter;
use App\Support\Runtime\LlmCompletion;
use App\Support\Runtime\LlmProviderVerifier;
use App\Support\Runtime\LlmRequest;
use App\Support\Runtime\RuntimeAgent;
use App\Support\Secrets\Contracts\SecretVault;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Ai;

/**
 * The verifier runs a live connection check through the real router path and
 * classifies the result so the console can point the operator at the exact
 * field to fix — the load-bearing safeguard behind the publish gate.
 */
function openAiProvider(array $overrides = []): LlmProvider
{
    return LlmProvider::factory()->draft()->create(array_merge([
        'provider' => 'OpenAI',
        'code' => 'gpt-5.4',
    ], $overrides));
}

function bindVaultKey(LlmProvider $provider, string $key): void
{
    $secret = app(SecretVault::class)->store(
        $provider->team,
        VaultSecretKind::LlmKey->reference($provider->slug),
        'OpenAI key',
        VaultSecretKind::LlmKey,
        $key,
    );

    $provider->update(['vault_secret_id' => $secret->id]);
    $provider->refresh();
}

test('a provider with no key resolves to missing key without a network call', function () {
    config(['ai.providers.openai.key' => null]);
    Http::fake(fn () => throw new RuntimeException('the verifier must not call the provider without a key'));

    $result = app(LlmProviderVerifier::class)->verify(openAiProvider());

    expect($result->outcome)->toBe(LlmVerificationOutcome::MissingKey)
        ->and($result->passed())->toBeFalse()
        ->and($result->toArray()['focus'])->toBe('key');
});

test('a working provider verifies and persists a passing result', function () {
    $provider = openAiProvider();
    bindVaultKey($provider, 'sk-live-key');
    Ai::fakeAgent(RuntimeAgent::class, ['ok']);

    $result = app(LlmProviderVerifier::class)->verify($provider);

    expect($result->passed())->toBeTrue()
        ->and($result->outcome)->toBe(LlmVerificationOutcome::Ok)
        ->and($result->latencyMs)->toBeInt();

    $provider->refresh();
    expect($provider->verification_status)->toBe(LlmVerificationOutcome::Ok)
        ->and($provider->isVerified())->toBeTrue()
        ->and($provider->verified_at)->not->toBeNull()
        ->and($provider->verification_checked_at)->not->toBeNull();
});

test('a rejected API key is classified as an invalid-key problem', function () {
    config(['ai.providers.openai.key' => 'sk-bad-key']);
    Http::fake(fn () => Http::response(['error' => ['message' => 'Incorrect API key provided.']], 401));

    $provider = openAiProvider();
    $result = app(LlmProviderVerifier::class)->verify($provider);

    expect($result->outcome)->toBe(LlmVerificationOutcome::InvalidKey)
        ->and($result->passed())->toBeFalse()
        ->and($result->toArray()['focus'])->toBe('key')
        ->and($result->message)->toContain('Incorrect API key');

    $provider->refresh();
    expect($provider->isVerified())->toBeFalse()
        ->and($provider->verified_at)->toBeNull();
});

test('a prefixed model code is classified as an unknown-model problem', function () {
    // Reproduces the real incident: a catalog code of `openai/gpt-5.4` reaches
    // OpenAI verbatim and is rejected. The verifier now names the culprit.
    config(['ai.providers.openai.key' => 'sk-live-key']);
    Http::fake(fn () => Http::response([
        'error' => ['message' => "The requested model 'openai/gpt-5.4' does not exist."],
    ], 404));

    $result = app(LlmProviderVerifier::class)->verify(openAiProvider(['code' => 'openai/gpt-5.4']));

    expect($result->outcome)->toBe(LlmVerificationOutcome::UnknownModel)
        ->and($result->toArray()['focus'])->toBe('model')
        ->and($result->message)->toContain('does not exist');
});

test('a 400 bad request is classified as an unknown-model problem', function () {
    config(['ai.providers.openai.key' => 'sk-live-key']);
    Http::fake(fn () => Http::response(['error' => ['message' => 'Unsupported model.']], 400));

    $result = app(LlmProviderVerifier::class)->verify(openAiProvider());

    expect($result->outcome)->toBe(LlmVerificationOutcome::UnknownModel);
});

test('a 429 is classified as rate limited', function () {
    config(['ai.providers.openai.key' => 'sk-live-key']);
    Http::fake(fn () => Http::response(['error' => ['message' => 'Rate limit reached.']], 429));

    $result = app(LlmProviderVerifier::class)->verify(openAiProvider());

    expect($result->outcome)->toBe(LlmVerificationOutcome::RateLimited)
        ->and($result->toArray()['focus'])->toBe('retry');
});

test('a 402 is classified as no quota', function () {
    config(['ai.providers.openai.key' => 'sk-live-key']);
    Http::fake(fn () => Http::response(['error' => ['message' => 'You exceeded your current quota.']], 402));

    $result = app(LlmProviderVerifier::class)->verify(openAiProvider());

    expect($result->outcome)->toBe(LlmVerificationOutcome::NoQuota)
        ->and($result->toArray()['focus'])->toBe('billing');
});

test('a 503 is classified as unreachable', function () {
    config(['ai.providers.openai.key' => 'sk-live-key']);
    Http::fake(fn () => Http::response([], 503));

    $result = app(LlmProviderVerifier::class)->verify(openAiProvider());

    expect($result->outcome)->toBe(LlmVerificationOutcome::Unreachable)
        ->and($result->toArray()['focus'])->toBe('network');
});

test('a connection failure is classified as unreachable', function () {
    config(['ai.providers.openai.key' => 'sk-live-key']);
    Http::fake(fn () => throw new ConnectionException('Connection refused'));

    $result = app(LlmProviderVerifier::class)->verify(openAiProvider());

    expect($result->outcome)->toBe(LlmVerificationOutcome::Unreachable);
});

test('an unexpected status is classified as unknown', function () {
    config(['ai.providers.openai.key' => 'sk-live-key']);
    Http::fake(fn () => Http::response(['error' => ['message' => 'Teapot.']], 500));

    $result = app(LlmProviderVerifier::class)->verify(openAiProvider());

    expect($result->outcome)->toBe(LlmVerificationOutcome::Unknown);
});

test('an unrecognised error is classified as unknown', function () {
    config(['ai.providers.openai.key' => 'sk-live-key']);

    app()->bind(LlmRouter::class, fn () => new class implements LlmRouter
    {
        public function complete(LlmRequest $request): LlmCompletion
        {
            throw new RuntimeException('something unexpected');
        }
    });

    $result = app(LlmProviderVerifier::class)->verify(openAiProvider());

    expect($result->outcome)->toBe(LlmVerificationOutcome::Unknown)
        ->and($result->passed())->toBeFalse();
});

test('every verification outcome exposes a label, focus, and default message', function () {
    foreach (LlmVerificationOutcome::cases() as $outcome) {
        expect($outcome->label())->not->toBe('')
            ->and($outcome->focus())->not->toBe('')
            ->and($outcome->defaultMessage())->not->toBe('');
    }

    expect(LlmVerificationOutcome::Ok->isSuccess())->toBeTrue()
        ->and(LlmVerificationOutcome::InvalidKey->isSuccess())->toBeFalse();
});
