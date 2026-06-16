<?php

namespace App\Enums;

/**
 * Execution mode for a tool contract. Raw values match the console contract's
 * `execModeLabel` keys (resources/js/maac/data.ts).
 */
enum ExecMode: string
{
    case Hosted = 'hosted';
    case Client = 'client';
    case Http = 'http';
    case Connector = 'connector';
    case Knowledge = 'knowledge';
    case Db = 'db';

    /**
     * Get the human-readable label for the execution mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::Hosted => 'MAAC-hosted',
            self::Client => 'Client-side',
            self::Http => 'Remote HTTP',
            self::Connector => 'Connector server',
            self::Knowledge => 'Knowledge retrieval',
            self::Db => 'Read-only DB',
        };
    }

    /**
     * Determine whether the mode is executed by the calling application via the SDK.
     */
    public function isClientSide(): bool
    {
        return $this === self::Client;
    }

    /**
     * Get all execution modes as value/label option pairs.
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
