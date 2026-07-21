<?php

namespace App\Support\Runtime;

use App\Enums\TraceEventType;
use App\Models\AgentRun;
use App\Models\TraceEvent;
use App\Support\Governance\RunRedactor;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

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
        return DB::transaction(function () use ($run, $type, $message, $data): TraceEvent {
            $locked = AgentRun::query()->lockForUpdate()->findOrFail($run->id);
            $sequence = $locked->next_trace_sequence;
            $locked->increment('next_trace_sequence');
            $run->setAttribute('next_trace_sequence', $sequence + 1);

            return TraceEvent::query()->create([
                'agent_run_id' => $run->id,
                'type' => $type,
                'message' => $this->redactor->output($run, $message),
                'data' => $data === [] ? null : $this->redactor->result($run, $data),
                'sequence' => $sequence,
                'occurred_at' => Date::now(),
            ]);
        }, 3);
    }
}
