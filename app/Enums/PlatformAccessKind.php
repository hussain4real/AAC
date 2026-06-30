<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * How a MAAC platform-access grant was made (Phase 8B). A `standard` grant is a
 * deliberate, certifiable role assignment; a `break_glass` grant is time-boxed
 * emergency access that auto-expires and must be reviewed.
 */
enum PlatformAccessKind: string
{
    case Standard = 'standard';
    case BreakGlass = 'break_glass';

    /**
     * The human-readable label for the grant kind.
     */
    public function label(): string
    {
        return Str::headline($this->value);
    }

    /**
     * Whether this is an emergency break-glass grant.
     */
    public function isBreakGlass(): bool
    {
        return $this === self::BreakGlass;
    }
}
