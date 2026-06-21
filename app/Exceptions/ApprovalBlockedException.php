<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Thrown when an approval cannot be granted because the change has unmet
 * prerequisites (e.g. an agent publication whose tools are still awaiting
 * approval, are unimplemented in the target environment, or whose model is not
 * approved there).
 */
class ApprovalBlockedException extends RuntimeException
{
    /**
     * @param  array<int, string>  $blockers
     */
    public function __construct(public readonly array $blockers)
    {
        parent::__construct('The approval has unmet prerequisites.');
    }
}
