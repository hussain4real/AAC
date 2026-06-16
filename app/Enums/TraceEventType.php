<?php

namespace App\Enums;

use Illuminate\Support\Str;

/**
 * Trace event types recorded across an agent run lifecycle
 * (Architecture Document §11.3).
 */
enum TraceEventType: string
{
    case RunRequested = 'run_requested';
    case CallerAuthenticated = 'caller_authenticated';
    case ModelSelected = 'model_selected';
    case PromptPrepared = 'prompt_prepared';
    case ToolRequired = 'tool_required';
    case ToolResultReceived = 'tool_result_received';
    case Validated = 'validated';
    case Resumed = 'resumed';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Get the human-readable label for the trace event type.
     */
    public function label(): string
    {
        return Str::headline($this->value);
    }
}
