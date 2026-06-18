<?php

namespace App\Support\Runtime\HostedTools;

use App\Support\Runtime\Contracts\HostedTool;
use Illuminate\Support\Facades\Date;

/**
 * Built-in hosted tool that returns the current server time. Demonstrates a
 * MAAC-hosted utility that needs no arguments and no external dependencies.
 *
 * Contract shape: input `{}`, output `{ "iso": "string" }`.
 */
class CurrentTimeHostedTool implements HostedTool
{
    /**
     * Return the current time as an ISO-8601 string.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments): array
    {
        return [
            'iso' => Date::now()->toIso8601String(),
        ];
    }
}
