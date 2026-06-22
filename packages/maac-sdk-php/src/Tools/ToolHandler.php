<?php

declare(strict_types=1);

namespace Maac\Sdk\Tools;

/**
 * A local implementation of a client-side tool contract. This is where an
 * application's own business logic and data access live — MAAC never sees the
 * implementation, only the result it returns. The returned array must satisfy
 * the tool contract's output schema.
 */
interface ToolHandler
{
    /**
     * The slug of the tool contract this handler implements (as it appears in
     * the manifest, e.g. "fetch-records").
     */
    public function tool(): string;

    /**
     * Execute the tool with MAAC-supplied arguments and return the result.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments, ToolContext $context): array;
}
