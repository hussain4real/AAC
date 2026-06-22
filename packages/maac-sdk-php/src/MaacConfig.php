<?php

declare(strict_types=1);

namespace Maac\Sdk;

use Maac\Sdk\Exceptions\MaacException;

/**
 * Immutable connection configuration for a MAAC application credential. Build it
 * directly or from environment variables via {@see self::fromEnvironment()}.
 */
final class MaacConfig
{
    public function __construct(
        public readonly string $baseUrl,
        public readonly string $clientId,
        public readonly string $clientSecret,
        public readonly int $timeout = 30,
        public readonly int $connectTimeout = 10,
    ) {}

    /**
     * Build configuration from the documented MAAC_* environment variables.
     *
     * @param  array<string, string|false|null>  $env  Defaults to getenv().
     *
     * @throws MaacException when a required variable is missing
     */
    public static function fromEnvironment(?array $env = null): self
    {
        $read = static function (string $key) use ($env): ?string {
            $value = $env === null ? getenv($key) : ($env[$key] ?? null);

            return is_string($value) && $value !== '' ? $value : null;
        };

        $baseUrl = $read('MAAC_BASE_URL');
        $clientId = $read('MAAC_CLIENT_ID');
        $clientSecret = $read('MAAC_CLIENT_SECRET');

        $missing = [];

        foreach (['MAAC_BASE_URL' => $baseUrl, 'MAAC_CLIENT_ID' => $clientId, 'MAAC_CLIENT_SECRET' => $clientSecret] as $key => $value) {
            if ($value === null) {
                $missing[] = $key;
            }
        }

        if ($missing !== []) {
            throw new MaacException('Missing required MAAC environment variables: '.implode(', ', $missing).'.');
        }

        $timeout = $read('MAAC_TIMEOUT');

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
