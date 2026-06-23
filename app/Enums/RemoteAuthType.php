<?php

namespace App\Enums;

/**
 * How MAAC authenticates outbound calls to a remote HTTP endpoint or MCP
 * connector. The credential material itself is stored encrypted at rest and is
 * never returned to the console or the SDK.
 */
enum RemoteAuthType: string
{
    case None = 'none';
    case Bearer = 'bearer';
    case Header = 'header';

    /**
     * Get the human-readable label for the auth type.
     */
    public function label(): string
    {
        return match ($this) {
            self::None => 'No authentication',
            self::Bearer => 'Bearer token',
            self::Header => 'Custom header',
        };
    }

    /**
     * Get all auth types as value/label option pairs.
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
