<?php

declare(strict_types=1);

namespace Maacc\Sdk\Webhooks;

/**
 * Verifies (and, for tests, produces) the HMAC-SHA256 signature MAACC sends on
 * every webhook delivery. The signature is computed over `{timestamp}.{body}`,
 * so a receiver can reject replays outside a tolerance window. This mirrors
 * MAACC's server-side signer exactly and is pinned by the shared contract
 * fixtures.
 */
final class WebhookSignature
{
    /**
     * Compute the hex HMAC-SHA256 signature for a payload at a timestamp.
     */
    public static function sign(string $payload, string $timestamp, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    /**
     * Verify a received `X-Maacc-Signature` against the raw request body, the
     * `X-Maacc-Webhook-Timestamp` header, and the endpoint's signing secret,
     * within the given clock-skew tolerance (in seconds). The signature may
     * include the `sha256=` prefix.
     */
    public static function verify(
        string $payload,
        string $signature,
        string $timestamp,
        string $secret,
        int $toleranceSeconds = 300,
        ?int $now = null,
    ): bool {
        $now ??= time();

        if (! is_numeric($timestamp)) {
            return false;
        }

        if (abs($now - (int) $timestamp) > $toleranceSeconds) {
            return false;
        }

        $expected = self::sign($payload, $timestamp, $secret);
        $provided = str_starts_with($signature, 'sha256=') ? substr($signature, 7) : $signature;

        return hash_equals($expected, $provided);
    }
}
