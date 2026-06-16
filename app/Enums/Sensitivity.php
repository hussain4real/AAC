<?php

namespace App\Enums;

/**
 * Data sensitivity classification for tools, models, and agent runs.
 */
enum Sensitivity: string
{
    case Public = 'public';
    case Internal = 'internal';
    case Confidential = 'confidential';
    case Restricted = 'restricted';

    /**
     * Get the display label for the sensitivity level (matches the console contract).
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Get all sensitivity levels as value/label option pairs.
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
