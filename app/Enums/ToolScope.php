<?php

namespace App\Enums;

/**
 * Scope at which a tool contract is assigned: platform-wide, project, or single agent.
 */
enum ToolScope: string
{
    case Global = 'global';
    case Project = 'project';
    case Agent = 'agent';

    /**
     * Get the display label for the scope (matches the console contract casing).
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Get all scopes as value/label option pairs.
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
