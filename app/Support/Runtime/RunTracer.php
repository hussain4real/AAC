<?php

namespace App\Support\Runtime;

use App\Enums\TraceEventType;
use App\Models\AgentRun;
use App\Models\TraceEvent;
use App\Support\Governance\RunRedactor;
use Illuminate\Support\Facades\Date;

/**
 * Appends ordered {@see TraceEvent} records to an agent run, maintaining a
 * monotonic sequence so the run timeline can be replayed in order.
 */
class RunTracer
{
    public function __construct(private readonly RunRedactor $redactor) {}

    /**
     * Record a trace event for the run.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(AgentRun $run, TraceEventType $type, ?string $message = null, array $data = []): TraceEvent
    {
        $max = $run->traceEvents()->max('sequence');

        return $run->traceEvents()->create([
            'type' => $type,
            'message' => $this->redactor->output($run, $message),
            'data' => $data === [] ? null : $this->redactor->result($run, $data),
            'sequence' => $max === null ? 0 : ((int) $max) + 1,
            'occurred_at' => Date::now(),
        ]);
    }
}
