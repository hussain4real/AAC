<?php

namespace App\Enums;

/**
 * SDK languages MAAC can generate handler stubs for and that applications may
 * report their client-side tool implementations against.
 */
enum SdkLanguage: string
{
    case TypeScript = 'typescript';
    case Php = 'php';
    case Python = 'python';

    /**
     * Get the human-readable label for the language.
     */
    public function label(): string
    {
        return match ($this) {
            self::TypeScript => 'TypeScript',
            self::Php => 'PHP',
            self::Python => 'Python',
        };
    }

    /**
     * Get all languages as value/label option pairs.
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
