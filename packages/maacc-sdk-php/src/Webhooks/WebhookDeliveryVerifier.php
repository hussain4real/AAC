<?php

declare(strict_types=1);

namespace Maacc\Sdk\Webhooks;

/**
 * Receiver-side signature, delivery-ID, and sequence verifier. Applications
 * should replace the in-memory processed-ID set with durable storage across
 * processes, acknowledge duplicates with 2xx, and skip duplicate side effects.
 */
final class WebhookDeliveryVerifier
{
    /** @var array<string, true> */
    private array $processed = [];

    public function __construct(
        private readonly string $secret,
        private readonly int $toleranceSeconds = 300,
    ) {}

    /**
     * @param  array{signature: string, timestamp: string, delivery_id: string, sequence: string}  $headers
     * @return array{accepted: bool, duplicate: bool, delivery_id: string, sequence: int, payload?: array<string, mixed>}
     */
    public function verify(string $body, array $headers, ?int $now = null): array
    {
        $deliveryId = trim($headers['delivery_id']);
        $sequence = filter_var($headers['sequence'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $accepted = $deliveryId !== ''
            && is_int($sequence)
            && WebhookSignature::verify(
                $body,
                $headers['signature'],
                $headers['timestamp'],
                $this->secret,
                $this->toleranceSeconds,
                $now,
            );

        if (! $accepted) {
            return ['accepted' => false, 'duplicate' => false, 'delivery_id' => $deliveryId, 'sequence' => is_int($sequence) ? $sequence : 0];
        }

        $payload = json_decode($body, true);

        if (! is_array($payload)) {
            return ['accepted' => false, 'duplicate' => false, 'delivery_id' => $deliveryId, 'sequence' => $sequence];
        }

        $duplicate = isset($this->processed[$deliveryId]);
        $this->processed[$deliveryId] = true;

        return [
            'accepted' => true,
            'duplicate' => $duplicate,
            'delivery_id' => $deliveryId,
            'sequence' => $sequence,
            'payload' => $payload,
        ];
    }
}
