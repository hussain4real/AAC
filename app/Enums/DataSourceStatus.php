<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Lifecycle of a registered read-only data source. A source starts as a draft
 * while it is reviewed; it must be active before the runtime may query it. A
 * sensitive source (or one explicitly flagged) is gated behind a data-source
 * access approval and stays a draft until that approval is granted. A disabled
 * source is retained but cannot back a `db` tool query.
 */
enum DataSourceStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Disabled = 'disabled';

    /**
     * Get the human-readable label for the source status.
     */
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /**
     * Whether the source may be queried by the runtime.
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
