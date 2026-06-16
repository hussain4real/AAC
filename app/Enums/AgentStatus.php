<?php

namespace App\Enums;

/**
 * Lifecycle status of an agent.
 */
enum AgentStatus: string
{
    case Draft = 'draft';
    case Testing = 'testing';
    case Published = 'published';
    case Disabled = 'disabled';

    /**
     * Get the display label for the status (matches the console contract casing).
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Determine whether agents in this status are live for production callers.
     */
    public function isPublished(): bool
    {
        return $this === self::Published;
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
