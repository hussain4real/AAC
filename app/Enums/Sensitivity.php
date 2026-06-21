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
     * Rank the sensitivity so levels can be compared (higher = more sensitive).
     */
    public function level(): int
    {
        return match ($this) {
            self::Public => 0,
            self::Internal => 1,
            self::Confidential => 2,
            self::Restricted => 3,
        };
    }

    /**
     * Determine whether this level is at least as sensitive as the given one.
     */
    public function isAtLeast(self $other): bool
    {
        return $this->level() >= $other->level();
    }

    /**
     * Determine whether payloads at this level should be masked before storage
     * (Confidential and above).
     */
    public function requiresMasking(): bool
    {
        return $this->isAtLeast(self::Confidential);
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
