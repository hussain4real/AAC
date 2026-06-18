<?php

namespace App\Support\Runtime\Contracts;

/**
 * A MAAC-hosted tool: a simple built-in utility the runtime executes inside the
 * platform (as opposed to client-side tools, which run in the calling
 * application via the SDK). The returned array must satisfy the tool contract's
 * output schema.
 */
interface HostedTool
{
    /**
     * Execute the tool against the model-supplied arguments.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments): array;
}
