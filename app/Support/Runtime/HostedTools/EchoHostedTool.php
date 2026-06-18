<?php

namespace App\Support\Runtime\HostedTools;

use App\Support\Runtime\Contracts\HostedTool;

/**
 * Built-in hosted tool that returns the supplied message back to the model.
 * Useful as a no-dependency utility and as a reference hosted implementation.
 *
 * Contract shape: input `{ "message": "string" }`, output `{ "message": "string" }`.
 */
class EchoHostedTool implements HostedTool
{
    /**
     * Echo the supplied message.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments): array
    {
        return [
            'message' => (string) ($arguments['message'] ?? ''),
        ];
    }
}
