<?php

namespace App\Support\Runtime;

use App\Enums\TraceEventType;
use App\Models\AgentRun;
use App\Models\TraceEvent;
use Illuminate\Support\Facades\Date;

/**
 * Appends ordered {@see TraceEvent} records to an agent run, maintaining a
 * monotonic sequence so the run timeline can be replayed in order.
 */
class RunTracer
{
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
            'message' => $message,
            'data' => $data === [] ? null : $data,
            'sequence' => $max === null ? 0 : ((int) $max) + 1,
            'occurred_at' => Date::now(),
        ]);
    }
}
