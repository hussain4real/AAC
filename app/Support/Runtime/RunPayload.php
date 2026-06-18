<?php

namespace App\Support\Runtime;

use App\Enums\RunStatus;
use App\Models\AgentRun;
use App\Models\ToolCall;

/**
 * Serializes an {@see AgentRun} into the SDK/runtime response envelope. The
 * shape varies by status: a completed run carries the response, a paused run
 * carries the pending tool request, and a terminal failure carries the error.
 */
class RunPayload
{
    /**
     * Build the response envelope for the given run.
     *
     * @return array<string, mixed>
     */
    public static function for(AgentRun $run): array
    {
        $payload = [
            'run_id' => $run->slug,
            'agent_slug' => $run->agent->agent_slug,
            'status' => $run->status->value,
            'usage' => [
                'tokens_in' => $run->tokens_in,
                'tokens_out' => $run->tokens_out,
            ],
            'cost' => $run->cost,
        ];

        return match ($run->status) {
            RunStatus::Completed => [...$payload, 'response' => $run->output],
            RunStatus::WaitingForClient => [...$payload, 'tool_call' => self::toolCall($run)],
            RunStatus::Failed, RunStatus::Expired, RunStatus::Cancelled => [...$payload, 'error' => $run->error],
            default => $payload,
        };
    }

    /**
     * Describe the client-side tool call the run is paused on.
     *
     * @return array<string, mixed>|null
     */
    private static function toolCall(AgentRun $run): ?array
    {
        $call = $run->pendingToolCalls()->with('toolContract')->latest('sequence')->first();

        if (! $call instanceof ToolCall) {
            return null;
        }

        return [
            'id' => $call->id,
            'tool' => $call->tool_name,
            'arguments' => $call->arguments,
            'output_schema' => $call->toolContract?->output_schema,
        ];
    }
}
