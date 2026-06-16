<?php

namespace App\Enums;

/**
 * Lifecycle status of a project.
 */
enum ProjectStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    /**
     * Get the display label for the status (matches the console contract casing).
     */
    public function label(): string
    {
        return ucfirst($this->value);
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
