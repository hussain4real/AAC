<?php

declare(strict_types=1);

namespace Maacc\Sdk;

use Maacc\Sdk\Exceptions\MaaccException;

/**
 * Immutable connection configuration for a MAACC application credential. Build it
 * directly or from environment variables via {@see self::fromEnvironment()}.
 */
final class MaaccConfig
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly int $timeout = 30,
        public readonly int $connectTimeout = 10,
    ) {}

    /**
     * Build configuration from the documented MAACC_* environment variables.
     *
     * @param  array<string, string|false|null>  $env  Defaults to getenv().
     *
     * @throws MaaccException when a required variable is missing
     */
    public static function fromEnvironment(?array $env = null): self
    {
        $read = static function (string $key) use ($env): ?string {
            $value = $env === null ? getenv($key) : ($env[$key] ?? null);

            return is_string($value) && $value !== '' ? $value : null;
        };

        $baseUrl = $read('MAACC_BASE_URL');
        $clientId = $read('MAACC_CLIENT_ID');
        $clientSecret = $read('MAACC_CLIENT_SECRET');

        $missing = [];

        foreach (['MAACC_BASE_URL' => $baseUrl, 'MAACC_CLIENT_ID' => $clientId, 'MAACC_CLIENT_SECRET' => $clientSecret] as $key => $value) {
            if ($value === null) {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            throw new MaaccException('Missing required MAACC environment variables: '.implode(', ', $missing).'.');
        }

        $timeout = $read('MAACC_TIMEOUT');

        return new self(
            baseUrl: $baseUrl,
            clientId: $clientId,
            clientSecret: $clientSecret,
            timeout: $timeout !== null ? (int) $timeout : 30,
        );
    }

    /**
     * The fully-qualified URL for an API path, normalising slashes.
     */
    public function url(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }
}
