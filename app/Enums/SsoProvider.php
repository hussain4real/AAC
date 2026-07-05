<?php

namespace App\Enums;

use App\Models\SsoConnection;

/**
 * The protocol an enterprise {@see SsoConnection} speaks. MAACC implements the
 * OAuth 2.0 / OIDC authorization-code flow (authorize → token → userinfo) over
 * the HTTP client, so both providers drive the same flow against the connection's
 * configured endpoints; the distinction is metadata for the console.
 */
enum SsoProvider: string
{
    case Oidc = 'oidc';
    case Oauth2 = 'oauth2';

    /**
     * Get the display label for the provider.
     */
    public function label(): string
    {
        return match ($this) {
            self::Oidc => 'OpenID Connect',
            self::Oauth2 => 'OAuth 2.0',
        };
    }

    /**
     * Get all providers as value/label option pairs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
