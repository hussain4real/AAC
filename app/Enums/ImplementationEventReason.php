<?php

namespace App\Enums;

use App\Models\ToolImplementationEvent;

/**
 * Why a {@see ToolImplementationEvent} was appended to a client-side
 * tool's implementation timeline: the application's SDK reported a handler, a
 * contract change forced a re-evaluation of the existing handler, or the tool
 * executed successfully with a schema-valid result during a live run.
 */
enum ImplementationEventReason: string
{
    case Reported = 'reported';
    case ContractChanged = 'contract_changed';
    case RuntimeValidated = 'runtime_validated';

    /**
     * Get the human-readable label for the event reason.
     */
    public function label(): string
    {
        return match ($this) {
            self::Reported => 'Reported by SDK',
            self::ContractChanged => 'Contract changed',
            self::RuntimeValidated => 'Validated by a live run',
        };
    }
}
