<?php

namespace App\Support\Webhooks;

/**
 * Signs and verifies MAACC webhook payloads with an HMAC-SHA256 signature over
 * `{timestamp}.{body}`. Binding the timestamp into the signed material lets a
 * receiver reject replays outside a tolerance window. The same algorithm is
 * ported into the PHP and TypeScript SDKs (and pinned by the shared contract
 * fixtures) so a receiver can verify a delivery without MAACC internals.
 */
class WebhookSigner
{
    /**
     * Compute the hex HMAC-SHA256 signature for a payload at a timestamp.
     */
    public static function sign(string $payload, string $timestamp, string $secret): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$payload, $secret);
    }

    /**
     * Format the signature for the `X-Maacc-Signature` header.
     */
    public static function header(string $signature): string
    {
        return 'sha256='.$signature;
    }

    /**
     * Verify a received signature against the payload, timestamp, and secret,
     * within the given clock-skew tolerance (in seconds). The `X-Maacc-Signature`
     * header value may include the `sha256=` prefix.
     */
    public static function verify(
        string $payload,
        string $signature,
        string $timestamp,
        string $secret,
        int $toleranceSeconds,
        int $now,
    ): bool {
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
