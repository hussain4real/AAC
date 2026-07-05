<?php

declare(strict_types=1);

namespace Maacc\Reference\Cli;

use Maacc\Sdk\Webhooks\WebhookSignature;

/**
 * A minimal inbound webhook receiver for the plain-PHP reference consumer. It
 * verifies the HMAC signature MAACC sends with every delivery before trusting the
 * payload — the pattern every application's webhook endpoint must follow.
 */
final class WebhookReceiver
{
    public function __construct(private readonly string $signingSecret) {}

    /**
     * Verify a delivery's signature and return its decoded body, or null if the
     * signature is invalid or stale (the receiver must then reject the request).
     *
     * @param  array<string, string>  $headers
     * @return array<string, mixed>|null
     */
    public function handle(string $body, array $headers): ?array
    {
        $signature = $headers['X-Maacc-Signature'] ?? '';
        $timestamp = $headers['X-Maacc-Webhook-Timestamp'] ?? '';

        if (! WebhookSignature::verify($body, $signature, $timestamp, $this->signingSecret)) {
            return null;
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : null;
    }
}
