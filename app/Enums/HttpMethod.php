<?php

namespace App\Enums;

/**
 * The HTTP method a remote HTTP tool may use. Constraining the method is part of
 * the tool contract's egress policy (a read tool should not be able to POST).
 */
enum HttpMethod: string
{
    case Get = 'get';
    case Post = 'post';
    case Put = 'put';
    case Patch = 'patch';
    case Delete = 'delete';

    /**
     * Get the display label (the conventional upper-case verb) for the method.
     */
    public function label(): string
    {
        return strtoupper($this->value);
    }

    /**
     * Get all methods as value/label option pairs.
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
