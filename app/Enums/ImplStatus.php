<?php

namespace App\Enums;

/**
 * Client-side tool implementation status. Raw values match the console
 * contract's `implLabel` keys (resources/js/maac/data.ts).
 */
enum ImplStatus: string
{
    case Ready = 'ready';
    case Implemented = 'implemented';
    case Required = 'required';
    case Outdated = 'outdated';
    case Incompatible = 'incompatible';
    case Disabled = 'disabled';
    case NotApplicable = 'n/a';

    /**
     * Get the human-readable label for the implementation status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Ready => 'Ready',
            self::Implemented => 'Implemented',
            self::Required => 'Requires implementation',
            self::Outdated => 'Outdated',
            self::Incompatible => 'Incompatible',
            self::Disabled => 'Disabled',
            self::NotApplicable => 'Not required',
        };
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
