<?php

namespace App\Enums;

/**
 * Status of an individual tool call within an agent run.
 */
enum ToolCallStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';

    /**
     * Get the display label for the status.
     */
    public function label(): string
    {
        return ucfirst($this->value);
    }
}
