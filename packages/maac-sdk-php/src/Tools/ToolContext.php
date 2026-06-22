<?php

declare(strict_types=1);

namespace Maac\Sdk\Tools;

use Maac\Sdk\Resources\Run;
use Maac\Sdk\Resources\ToolCall;

/**
 * The context handed to a {@see ToolHandler} when MAAC pauses a run for it:
 * the originating tool call (with its output schema) and the current run state.
 */
final class ToolContext
{
    public function __construct(
        public readonly Run $run,
        public readonly ToolCall $toolCall,
    ) {}
}
