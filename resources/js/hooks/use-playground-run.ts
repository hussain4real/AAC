import type { FormDataConvertible } from '@inertiajs/core';
import { useHttp, usePage } from '@inertiajs/react';
import { useCallback, useState } from 'react';
import {
    store,
    toolResult,
} from '@/actions/App/Http/Controllers/Maac/PlaygroundRunController';

/**
 * One ordered trace event recorded during a run, as surfaced to the console.
 */
export interface PlaygroundTraceEntry {
    id: string;
    type: string;
    label: string;
    message: string;
    data: Record<string, unknown> | null;
    sequence: number;
    occurredAt: string | null;
}

/**
 * The client-side tool call a paused run is waiting on.
 */
export interface PlaygroundToolCall {
    id: string;
    tool: string;
    arguments: Record<string, unknown>;
    output_schema: Record<string, string> | null;
}

/**
 * The console playground run envelope (the SDK run shape plus the trace
 * timeline, resolved model, and measured latency).
 */
export interface PlaygroundRunResult {
    run_id: string;
    agent_slug: string;
    status: string;
    usage: { tokens_in: number; tokens_out: number };
    cost: number;
    model: string;
    latency_ms: number | null;
    trace: PlaygroundTraceEntry[];
    response?: string;
    error?: string;
    tool_call?: PlaygroundToolCall | null;
}

export type PlaygroundEnvironment =
    'development' | 'sandbox' | 'staging' | 'production';

export interface UsePlaygroundRunReturn {
    run: PlaygroundRunResult | null;
    error: string | null;
    processing: boolean;
    start: (
        agentId: string,
        input: string,
        environment: PlaygroundEnvironment,
    ) => Promise<void>;
    submitToolResult: (
        runId: string,
        toolCallId: string,
        result: Record<string, FormDataConvertible>,
    ) => Promise<void>;
    reset: () => void;
}

const JSON_HEADERS = { Accept: 'application/json' } as const;

/**
 * Drives a real agent run from the console playground against the live runtime
 * (the same {@see \App\Support\Runtime\AgentRunner} the SDK uses). The first
 * call starts the run; if it pauses for a client-side tool, the caller submits
 * the tool result to resume it. There is no simulation — every value rendered
 * comes from the runtime.
 */
export function usePlaygroundRun(): UsePlaygroundRunReturn {
    const { currentTeam } = usePage().props;
    const runHttp = useHttp<
        { input: string; environment: PlaygroundEnvironment },
        PlaygroundRunResult
    >({
        input: '',
        environment: 'production',
    });
    // `result` is typed as `object` (not a recursive `FormDataConvertible` map)
    // purely to keep useHttp's mapped form types from instantiating infinitely;
    // the public API below still accepts a typed JSON object.
    const toolHttp = useHttp<
        { tool_call_id: string; result: object },
        PlaygroundRunResult
    >({ tool_call_id: '', result: {} });

    const [run, setRun] = useState<PlaygroundRunResult | null>(null);
    const [error, setError] = useState<string | null>(null);

    const start = useCallback(
        async (
            agentId: string,
            input: string,
            environment: PlaygroundEnvironment,
        ): Promise<void> => {
            if (!currentTeam) {
                return;
            }

            setError(null);
            setRun(null);

            try {
                runHttp.transform(() => ({ input, environment }));
                const result = await runHttp.submit(
                    store({ current_team: currentTeam.slug, agent: agentId }),
                    { headers: JSON_HEADERS },
                );
                setRun(result);
            } catch {
                setError(
                    'The run could not be completed. Check the agent is published and its model is configured.',
                );
            }
        },
        [currentTeam, runHttp],
    );

    const submitToolResult = useCallback(
        async (
            runId: string,
            toolCallId: string,
            result: Record<string, FormDataConvertible>,
        ): Promise<void> => {
            if (!currentTeam) {
                return;
            }

            setError(null);

            try {
                toolHttp.transform(() => ({
                    tool_call_id: toolCallId,
                    result,
                }));
                const updated = await toolHttp.submit(
                    toolResult({ current_team: currentTeam.slug, run: runId }),
                    { headers: JSON_HEADERS },
                );
                setRun(updated);
            } catch {
                setError('The tool result was rejected by the runtime.');
            }
        },
        [currentTeam, toolHttp],
    );

    const reset = useCallback((): void => {
        setRun(null);
        setError(null);
    }, []);

    return {
        run,
        error,
        processing: runHttp.processing || toolHttp.processing,
        start,
        submitToolResult,
        reset,
    };
}
