<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Whether a registered MCP connector is currently usable by the runtime. A
 * disabled connector is retained (with its discovered capabilities) but cannot
 * back a tool call until it is re-enabled.
 */
enum McpConnectorStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';

    /**
     * Get the human-readable label for the connector status.
     */
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /**
     * Whether the connector may be invoked by the runtime.
     */
    public function isActive(): bool
    {
        return $this === self::Active;
    }

    /**
     * Get all statuses as value/label option pairs.
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
