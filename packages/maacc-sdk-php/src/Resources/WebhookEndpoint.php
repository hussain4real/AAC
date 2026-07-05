<?php

declare(strict_types=1);

namespace Maacc\Sdk\Resources;

/**
 * A webhook endpoint registered with MAACC for the application's environment. The
 * signing {@see self::$secret} is present only on the response that registers
 * the endpoint and is never returned again.
 */
final class WebhookEndpoint
{
    /**
     * @param  array<int, string>  $events
     */
    public function __construct(
        public readonly string $id,
        public readonly string $url,
        public readonly array $events,
        public readonly string $environment,
        public readonly string $status,
        public readonly ?string $secret = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $events = is_array($data['events'] ?? null) ? array_values(array_map('strval', $data['events'])) : [];

        return new self(
            id: (string) ($data['id'] ?? ''),
            url: (string) ($data['url'] ?? ''),
            events: $events,
            environment: (string) ($data['environment'] ?? ''),
            status: (string) ($data['status'] ?? ''),
            secret: is_string($data['secret'] ?? null) ? $data['secret'] : null,
        );
    }
}
