<?php

declare(strict_types=1);

namespace Maacc\Sdk\Tools;

use Maacc\Sdk\Resources\Run;
use Maacc\Sdk\Resources\ToolCall;

/**
 * The context handed to a {@see ToolHandler} when MAACC pauses a run for it:
 * the originating tool call (with its output schema) and the current run state.
 */
final class ToolContext
{
    /** @var array<string, mixed> */
    public readonly array $callerContext;

    /** @param array<string, mixed>|null $callerContext */
    public function __construct(
        public readonly Run $run,
        public readonly ToolCall $toolCall,
        ?array $callerContext = null,
    ) {
        $this->callerContext = $callerContext ?? $run->callerContext;
    }
}
