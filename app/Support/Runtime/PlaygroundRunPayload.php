<?php

namespace App\Support\Runtime;

use App\Http\Resources\Maacc\TraceEventResource;
use App\Models\AgentRun;

/**
 * Serializes an {@see AgentRun} for the console playground: the same SDK/runtime
 * envelope returned to applications ({@see RunPayload}), enriched with the data
 * the live console view renders — the ordered trace timeline, the resolved
 * model, and the measured latency.
 */
class PlaygroundRunPayload
{
    /**
     * Build the console response envelope for the given run.
     *
     * @return array<string, mixed>
     */
    public static function for(AgentRun $run): array
    {
        $run->loadMissing(['agent', 'llmProvider']);

        return [
            ...RunPayload::for($run),
            'model' => $run->llmProvider->name,
            'latency_ms' => $run->latency_ms,
            'trace' => TraceEventResource::collection(
                $run->traceEvents()->orderBy('sequence')->get()
            )->resolve(),
        ];
    }
}
