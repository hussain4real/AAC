<?php

declare(strict_types=1);

namespace Maac\Sdk\Auth;

/**
 * A short-lived OAuth2 client_credentials access token, with the absolute epoch
 * second it should be considered expired (computed with a safety margin so the
 * SDK refreshes slightly before MAAC actually rejects it).
 */
final class AccessToken
{
    public function __construct(
        public readonly string $token,
        public readonly int $expiresAt,
    ) {}

    /**
     * Build a token from MAAC's `/oauth/token` envelope.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromTokenResponse(array $payload, int $now): self
    {
        $expiresIn = is_numeric($payload['expires_in'] ?? null) ? (int) $payload['expires_in'] : 3600;
        $safetyMargin = 30;

        return new self(
            token: (string) ($payload['access_token'] ?? ''),
            expiresAt: $now + max(0, $expiresIn - $safetyMargin),
        );
    }

    /**
     * Whether the token has passed (or is within the safety margin of) expiry.
     */
    public function isExpired(int $now): bool
    {
        return $now >= $this->expiresAt;
    }
}
