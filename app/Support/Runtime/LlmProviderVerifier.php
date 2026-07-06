<?php

namespace App\Support\Runtime;

use App\Enums\LlmVerificationOutcome;
use App\Models\LlmProvider;
use App\Support\Runtime\Contracts\LlmRouter;
use App\Support\Secrets\Contracts\SecretVault;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\InsufficientCreditsException;
use Laravel\Ai\Exceptions\ProviderOverloadedException;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

/**
 * Runs a cheap, live connection check against an LLM catalog entry using the
 * exact production router path — the same driver, model code, and vault key a
 * real run would use — then classifies the result so the console can tell the
 * operator whether the API key, the model code, or the network is at fault
 * instead of surfacing a raw provider error. A passing check is the gate a
 * model must clear before it can be published to the catalog.
 */
final class LlmProviderVerifier
{
    public function __construct(
        private readonly LlmRouter $router,
        private readonly SecretVault $vault,
    ) {}

    /**
     * Verify the provider, persist the outcome on the model, and return the
     * classified result.
     */
    public function verify(LlmProvider $provider): LlmVerificationResult
    {
        $result = $this->probe($provider);

        $provider->recordVerification($result->outcome, $result->message, now());

        return $result;
    }

    /**
     * Perform the live probe without persisting anything.
     */
    private function probe(LlmProvider $provider): LlmVerificationResult
    {
        $driver = $provider->driver();
        $key = $provider->resolveApiKey($this->vault) ?? (string) config("ai.providers.{$driver}.key");

        // The deterministic router (fake/validation mode) needs no real key, so
        // only a live provider is held to the missing-key check.
        if (trim($key) === '' && ! $this->router instanceof DeterministicLlmRouter) {
            return new LlmVerificationResult(
                LlmVerificationOutcome::MissingKey,
                LlmVerificationOutcome::MissingKey->defaultMessage(),
            );
        }

        $startedAt = hrtime(true);

        try {
            $this->router->complete($this->probeRequest($provider, $driver, $key));
        } catch (Throwable $exception) {
            return $this->classify($exception);
        }

        $latencyMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

        return new LlmVerificationResult(
            LlmVerificationOutcome::Ok,
            LlmVerificationOutcome::Ok->defaultMessage(),
            $latencyMs,
        );
    }

    /**
     * Build the minimal, cheap probe request.
     */
    private function probeRequest(LlmProvider $provider, string $driver, string $key): LlmRequest
    {
        return new LlmRequest(
            providerDriver: $driver,
            modelCode: $provider->code,
            systemPrompt: 'You are a connectivity probe. Reply with the single word: ok.',
            messages: [LlmMessage::user('Reply with the word: ok')],
            temperature: 0.0,
            maxTokens: 16,
            timeoutSeconds: (int) config('maacc.runtime.verify_timeout_seconds', 15),
            apiKey: $key !== '' ? $key : null,
        );
    }

    /**
     * Classify a thrown provider error into a verification outcome, walking the
     * exception chain so a wrapped HTTP error is still recognised.
     */
    private function classify(Throwable $exception): LlmVerificationResult
    {
        foreach ($this->chain($exception) as $error) {
            if ($error instanceof RateLimitedException) {
                return $this->fail(LlmVerificationOutcome::RateLimited, $exception);
            }

            if ($error instanceof InsufficientCreditsException) {
                return $this->fail(LlmVerificationOutcome::NoQuota, $exception);
            }

            if ($error instanceof ProviderOverloadedException) {
                return $this->fail(LlmVerificationOutcome::Unreachable, $exception);
            }

            if ($error instanceof ConnectionException) {
                return $this->fail(LlmVerificationOutcome::Unreachable, $exception);
            }

            if ($error instanceof RequestException && $error->response !== null) {
                return $this->fail($this->outcomeForStatus($error->response->status()), $exception);
            }
        }

        return $this->fail(LlmVerificationOutcome::Unknown, $exception);
    }

    /**
     * Map an HTTP status code to a verification outcome.
     */
    private function outcomeForStatus(int $status): LlmVerificationOutcome
    {
        return match (true) {
            $status === 401 || $status === 403 => LlmVerificationOutcome::InvalidKey,
            $status === 402 => LlmVerificationOutcome::NoQuota,
            $status === 429 => LlmVerificationOutcome::RateLimited,
            $status === 400 || $status === 404 || $status === 422 => LlmVerificationOutcome::UnknownModel,
            default => LlmVerificationOutcome::Unknown,
        };
    }

    /**
     * Build a failure result, enriching the outcome's default guidance with the
     * provider's own error text when it exposes one.
     */
    private function fail(LlmVerificationOutcome $outcome, Throwable $exception): LlmVerificationResult
    {
        $detail = $this->providerDetail($exception);

        $message = $detail === null
            ? $outcome->defaultMessage()
            : $outcome->defaultMessage().' Provider said: '.$detail;

        return new LlmVerificationResult($outcome, $message);
    }

    /**
     * Extract the provider's own error message from an HTTP error, if present.
     */
    private function providerDetail(Throwable $exception): ?string
    {
        foreach ($this->chain($exception) as $error) {
            if ($error instanceof RequestException && $error->response !== null) {
                $message = $error->response->json('error.message');

                if (is_string($message) && trim($message) !== '') {
                    return Str::limit(trim($message), 300);
                }
            }
        }

        return null;
    }

    /**
     * The exception and its `previous` chain, so a wrapped error is inspectable.
     *
     * @return list<Throwable>
     */
    private function chain(Throwable $exception): array
    {
        $chain = [];

        for ($error = $exception; $error !== null; $error = $error->getPrevious()) {
            $chain[] = $error;
        }

        return $chain;
    }
}
