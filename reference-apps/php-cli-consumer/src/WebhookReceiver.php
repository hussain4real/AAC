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
    /** @var array<string, true> */
    private array $processedDeliveryIds = [];

    private bool $duplicate = false;

    private ?string $deliveryId = null;

    private ?int $sequence = null;

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
        $deliveryId = $headers['X-Maacc-Webhook-Delivery'] ?? '';
        $sequence = $headers['X-Maacc-Webhook-Sequence'] ?? '';

        if ($deliveryId === '' || ! ctype_digit($sequence) || ! WebhookSignature::verify($body, $signature, $timestamp, $this->signingSecret)) {
            return null;
        }

        $decoded = json_decode($body, true);

        if (! is_array($decoded)) {
            return null;
        }

        $this->deliveryId = $deliveryId;
        $this->sequence = (int) $sequence;
        $this->duplicate = isset($this->processedDeliveryIds[$deliveryId]);
        $this->processedDeliveryIds[$deliveryId] = true;

        return $decoded;
    }

    public function wasDuplicate(): bool
    {
        return $this->duplicate;
    }

    public function deliveryId(): ?string
    {
        return $this->deliveryId;
    }

    public function sequence(): ?int
    {
        return $this->sequence;
    }
}
