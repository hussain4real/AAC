<?php

namespace App\Enums;

enum PayloadHandling: string
{
    case Store = 'store';
    case Mask = 'mask';
    case Exclude = 'exclude';

    public function label(): string
    {
        return match ($this) {
            self::Store => 'Store',
            self::Mask => 'Mask',
            self::Exclude => 'Exclude',
        };
    }
}
