<?php

namespace App\Enums;

/**
 * Deployment environment an application, credential, agent, or run belongs to.
 */
enum Environment: string
{
    case Development = 'development';
    case Sandbox = 'sandbox';
    case Staging = 'staging';
    case Production = 'production';

    /**
     * Get the display label for the environment (matches the console contract).
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }

    /**
     * Get all environments as value/label option pairs.
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
