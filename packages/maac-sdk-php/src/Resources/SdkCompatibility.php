<?php

declare(strict_types=1);

namespace Maac\Sdk\Resources;

use Maac\Sdk\MaacClient;

/**
 * MAAC's verdict on whether the installed SDK client is compatible with a MAAC
 * instance, returned by {@see MaacClient::compatibility()}. It answers
 * the headline question — "can my installed package talk to this MAAC?" — and
 * carries the API contract version, the supported client window, and any active
 * deprecations so a consumer can warn or block before invoking anything.
 */
final class SdkCompatibility
{
    public const STATUS_COMPATIBLE = 'compatible';

    public const STATUS_UPGRADE_REQUIRED = 'upgrade_required';

    public const STATUS_AHEAD = 'ahead';

    public const STATUS_UNKNOWN = 'unknown';

    /**
     * @param  array<int, array<string, mixed>>  $deprecations
     */
    public function __construct(
        public readonly bool $compatible,
        public readonly string $status,
        public readonly ?string $clientVersion,
        public readonly string $apiVersion,
        public readonly string $minimumClientVersion,
        public readonly string $currentClientVersion,
        public readonly bool $upgradeRequired,
        public readonly array $deprecations,
    ) {}

    /**
     * Build the verdict from the full `GET /api/v1/sdk` response.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $compatibility = is_array($data['compatibility'] ?? null) ? $data['compatibility'] : [];
        $deprecations = [];

        foreach (is_array($data['deprecations'] ?? null) ? $data['deprecations'] : [] as $deprecation) {
            if (is_array($deprecation)) {
                $deprecations[] = $deprecation;
            }
        }

        $clientVersion = $compatibility['client_version'] ?? null;

        return new self(
            compatible: (bool) ($compatibility['compatible'] ?? false),
            status: (string) ($compatibility['status'] ?? self::STATUS_UNKNOWN),
            clientVersion: is_string($clientVersion) ? $clientVersion : null,
            apiVersion: (string) ($compatibility['api_version'] ?? $data['api_version'] ?? ''),
            minimumClientVersion: (string) ($compatibility['minimum_client_version'] ?? $data['minimum_client_version'] ?? ''),
            currentClientVersion: (string) ($compatibility['current_client_version'] ?? $data['current_client_version'] ?? ''),
            upgradeRequired: (bool) ($compatibility['upgrade_required'] ?? false),
            deprecations: $deprecations,
        );
    }

    /**
     * Whether the installed client can safely talk to this MAAC instance.
     */
    public function isCompatible(): bool
    {
        return $this->compatible;
    }

    /**
     * Whether the installed client is below MAAC's supported minimum and must be
     * upgraded before it can be relied on.
     */
    public function requiresUpgrade(): bool
    {
        return $this->upgradeRequired;
    }
}
