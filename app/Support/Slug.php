<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Generates unique, URL-safe slugs used as MAAC route keys.
 */
class Slug
{
    /**
     * Build a slug from the base string that is unique within the given table.
     */
    public static function unique(string $table, string $base, string $column = 'slug'): string
    {
        $base = Str::slug($base) ?: Str::lower(Str::random(8));
        $slug = $base;
        $suffix = 2;

        while (DB::table($table)->where($column, $slug)->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }
}
