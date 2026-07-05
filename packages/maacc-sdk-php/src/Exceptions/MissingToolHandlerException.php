<?php

declare(strict_types=1);

namespace Maacc\Sdk\Exceptions;

/**
 * Thrown by the auto-resume run loop when MAACC pauses a run for a client-side
 * tool the application has not registered a handler for. Surfacing this loudly
 * (rather than hanging the run) is what lets a consumer catch an incomplete
 * integration during development.
 */
class MissingToolHandlerException extends MaaccException
{
    public function __construct(public readonly string $tool)
    {
        parent::__construct("No local handler is registered for the client-side tool [{$tool}].");
    }
}
