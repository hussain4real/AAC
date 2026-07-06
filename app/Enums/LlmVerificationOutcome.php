<?php

namespace App\Enums;

/**
 * The outcome of a live LLM provider connection check. It distinguishes an
 * API-key problem from a model/config problem from a transient/provider problem,
 * so the console can tell the operator exactly which field to fix rather than
 * surfacing a raw provider error.
 */
enum LlmVerificationOutcome: string
{
    case Ok = 'ok';
    case MissingKey = 'missing_key';
    case InvalidKey = 'invalid_key';
    case UnknownModel = 'unknown_model';
    case Unreachable = 'unreachable';
    case NoQuota = 'no_quota';
    case RateLimited = 'rate_limited';
    case Unknown = 'unknown';

    /**
     * Whether the provider responded successfully — the only outcome that
     * permits publishing a model to the catalog.
     */
    public function isSuccess(): bool
    {
        return $this === self::Ok;
    }

    /**
     * A short, human display label for the outcome.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ok => 'Connected',
            self::MissingKey => 'No API key',
            self::InvalidKey => 'API key rejected',
            self::UnknownModel => 'Unknown model',
            self::Unreachable => 'Unreachable',
            self::NoQuota => 'No quota',
            self::RateLimited => 'Rate limited',
            self::Unknown => 'Check failed',
        };
    }

    /**
     * Which part of the configuration the outcome points at, so the UI can steer
     * the operator to the right field: 'key', 'model', 'network', 'billing',
     * 'retry' (transient), or 'none' when the check passed.
     */
    public function focus(): string
    {
        return match ($this) {
            self::Ok => 'none',
            self::MissingKey, self::InvalidKey => 'key',
            self::UnknownModel => 'model',
            self::Unreachable => 'network',
            self::NoQuota => 'billing',
            self::RateLimited, self::Unknown => 'retry',
        };
    }

    /**
     * A default, provider-agnostic explanation used when the provider does not
     * return a more specific message of its own.
     */
    public function defaultMessage(): string
    {
        return match ($this) {
            self::Ok => 'The provider returned a response.',
            self::MissingKey => 'No API key is configured for this provider. Add a key, then verify again.',
            self::InvalidKey => 'The provider rejected the API key. Check the key and try again.',
            self::UnknownModel => 'The provider does not recognise this model code. Check the model code.',
            self::Unreachable => 'The provider could not be reached. Check the endpoint and network.',
            self::NoQuota => 'The API key is valid but the account has no available quota or billing.',
            self::RateLimited => 'The provider is rate limiting requests. Wait a moment and try again.',
            self::Unknown => 'The connection check failed for an unexpected reason.',
        };
    }
}
