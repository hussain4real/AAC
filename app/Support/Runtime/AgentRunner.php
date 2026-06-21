<?php

namespace App\Support\Runtime;

use App\Enums\AgentStatus;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\RunStatus;
use App\Enums\Sensitivity;
use App\Enums\ToolCallStatus;
use App\Enums\TraceEventType;
use App\Exceptions\Sdk\RuntimeRequestException;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\LlmProvider;
use App\Models\ToolCall;
use App\Models\ToolContract;
use App\Support\Governance\RunRedactor;
use App\Support\Runtime\Contracts\LlmRouter;
use App\Support\Runtime\HostedTools\HostedToolRegistry;
use App\Support\Sdk\ToolSchema;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;
use Throwable;

/**
 * Drives the MAAC agent run lifecycle: it creates the run, calls the LLM Router
 * one turn at a time, routes any requested tool by execution mode (executing
 * MAAC-hosted tools inline and pausing for client-side tools), validates every
 * payload at the boundary, and records a trace event for each milestone. The
 * runtime owns this loop precisely so it can pause and resume across requests.
 */
class AgentRunner
{
    public function __construct(
        private readonly LlmRouter $router,
        private readonly HostedToolRegistry $hostedTools,
        private readonly RunTracer $tracer,
        private readonly RunRedactor $redactor,
    ) {}

    /**
     * Create and drive a new run for the given agent and caller context.
     */
    public function start(Agent $agent, Application $application, Environment $environment, string $input, ?string $caller): AgentRun
    {
        $provider = $agent->llmProvider;

        $run = AgentRun::create([
            'agent_id' => $agent->id,
            'project_id' => $agent->project_id,
            'application_id' => $application->id,
            'llm_provider_id' => $provider->id,
            'slug' => 'run_'.Str::lower(Str::random(10)),
            'caller' => $caller,
            'environment' => $environment,
            'sensitivity' => $this->resolveSensitivity($agent),
            'status' => RunStatus::Queued,
            'tokens_in' => 0,
            'tokens_out' => 0,
            'cost' => 0,
            'tools' => [],
            'input' => $input,
            'state' => ['messages' => [LlmMessage::user($input)->toArray()], 'steps' => 0],
            'started_at' => Date::now(),
            'expires_at' => Date::now()->addSeconds($this->timeout()),
        ]);

        $run->setRelation('agent', $agent);

        // Apply the masking policy to the stored prompt (the live conversation
        // state keeps the raw value so the model still receives it).
        $run->update([
            'input' => $this->redactor->input($run, $input),
            'masked' => $this->redactor->applies($run),
        ]);

        $this->tracer->record($run, TraceEventType::RunRequested, 'Run requested.', ['caller' => $caller]);
        $this->tracer->record($run, TraceEventType::CallerAuthenticated, 'Caller authenticated.', [
            'application' => $application->slug,
            'environment' => $environment->value,
        ]);

        if (! $provider->isAvailableIn($environment->value)) {
            return $this->fail($run, 'model_unavailable', 'The agent model is not approved or available in this environment.');
        }

        $this->tracer->record($run, TraceEventType::ModelSelected, 'Model selected.', ['model' => $provider->code]);
        $this->tracer->record($run, TraceEventType::PromptPrepared, 'Prompt prepared.');

        $run->update(['status' => RunStatus::Running]);

        return $this->advance($run);
    }

    /**
     * Resolve the run's data sensitivity as the most sensitive level among the
     * agent and its assigned tools (tools and models are already classified).
     */
    private function resolveSensitivity(Agent $agent): Sensitivity
    {
        return $agent->tools->reduce(
            fn (Sensitivity $carry, ToolContract $tool): Sensitivity => $tool->sensitivity->level() > $carry->level()
                ? $tool->sensitivity
                : $carry,
            $agent->sensitivity,
        );
    }

    /**
     * Resume a paused run with a client-side tool result.
     *
     * @param  array<string, mixed>  $result
     *
     * @throws RuntimeRequestException
     */
    public function resume(AgentRun $run, string $toolCallId, array $result): AgentRun
    {
        $run->loadMissing(['agent.tools', 'agent.llmProvider']);

        if (! $run->isWaitingForClient()) {
            throw RuntimeRequestException::runNotWaiting();
        }

        if (($expired = $this->guardExpiry($run)) instanceof AgentRun) {
            return $expired;
        }

        $call = $run->pendingToolCalls()->where('id', $toolCallId)->with('toolContract')->first();
        $tool = $call?->toolContract;

        if (! $call instanceof ToolCall || ! $tool instanceof ToolContract) {
            throw RuntimeRequestException::runNotWaiting();
        }

        $this->validateClientResult($run, $tool, $call, $result);

        $this->completeToolCall($run, $call, $result);
        $this->tracer->record($run, TraceEventType::ToolResultReceived, "Client tool result received: {$call->tool_name}.", ['tool_call_id' => $call->id]);
        $this->tracer->record($run, TraceEventType::Validated, 'Tool result validated.');
        $this->appendMessage($run, LlmMessage::tool($call->tool_name, (string) json_encode($result)));
        $this->tracer->record($run, TraceEventType::Resumed, 'Run resumed after client tool.');

        $run->update(['status' => RunStatus::Running]);

        return $this->advance($run);
    }

    /**
     * Lazily expire a run that has passed its deadline (used on status reads).
     */
    public function refreshExpiry(AgentRun $run): AgentRun
    {
        return $this->guardExpiry($run) ?? $run;
    }

    /**
     * Run the model/tool loop until the run completes, pauses, or fails.
     */
    private function advance(AgentRun $run): AgentRun
    {
        $agent = $run->agent;

        while (true) {
            if (($expired = $this->guardExpiry($run)) instanceof AgentRun) {
                return $expired;
            }

            if ($agent->status !== AgentStatus::Published) {
                return $this->cancel($run);
            }

            if ($this->stepsTaken($run) >= $this->maxSteps()) {
                return $this->fail($run, 'step_limit_exceeded', 'The run exceeded the maximum number of steps.');
            }

            $completion = $this->runTurn($run, $agent);

            if (! $completion instanceof LlmCompletion) {
                return $run;
            }

            if (! $completion->isToolCall()) {
                return $this->complete($run, $completion->text ?? '');
            }

            if (($outcome = $this->handleToolCall($run, $agent, $completion)) instanceof AgentRun) {
                return $outcome;
            }
        }
    }

    /**
     * Ask the LLM Router for one turn, recording usage; null on model failure.
     */
    private function runTurn(AgentRun $run, Agent $agent): ?LlmCompletion
    {
        try {
            $completion = $this->router->complete($this->buildRequest($run, $agent));
        } catch (Throwable $exception) {
            $this->fail($run, 'model_error', $exception->getMessage());

            return null;
        }

        $this->recordUsage($run, $completion->usage, $agent->llmProvider);
        $this->incrementSteps($run);

        return $completion;
    }

    /**
     * Build the router request from the run state and agent configuration.
     */
    private function buildRequest(AgentRun $run, Agent $agent): LlmRequest
    {
        $provider = $agent->llmProvider;

        return new LlmRequest(
            providerDriver: $provider->driver(),
            modelCode: $provider->code,
            systemPrompt: $agent->system_prompt,
            messages: $this->messages($run),
            tools: $agent->tools->map(fn (ToolContract $tool): LlmToolDefinition => LlmToolDefinition::fromContract($tool))->all(),
            temperature: $agent->temperature,
            maxTokens: $agent->max_tokens,
            timeoutSeconds: $this->turnTimeout(),
        );
    }

    /**
     * Route a model-requested tool call. Returns the run when it pauses or
     * fails, or null when a hosted tool ran inline and the loop should continue.
     */
    private function handleToolCall(AgentRun $run, Agent $agent, LlmCompletion $completion): ?AgentRun
    {
        $tool = $agent->tools->firstWhere('slug', $completion->toolName);

        if (! $tool instanceof ToolContract) {
            return $this->fail($run, 'unknown_tool', "The model requested a tool that is not assigned to the agent: {$completion->toolName}.");
        }

        $arguments = $completion->toolArguments ?? [];
        $this->appendMessage($run, LlmMessage::assistant((string) json_encode(['tool' => $tool->slug, 'arguments' => $arguments])));

        $call = $this->recordToolCall($run, $tool, $arguments);
        $this->tracer->record($run, TraceEventType::ToolRequired, "Tool required: {$tool->slug}.", [
            'tool_call_id' => $call->id,
            'execution_mode' => $tool->execution_mode->value,
        ]);

        $errors = ToolSchema::validatePayload($tool->input_schema, $arguments);

        if ($errors !== []) {
            $this->failToolCall($call);

            return $this->fail($run, 'invalid_tool_arguments', 'The model produced arguments that do not satisfy the tool input schema.', ['errors' => $errors]);
        }

        return match ($tool->execution_mode) {
            ExecMode::Hosted => $this->executeHosted($run, $tool, $call, $arguments),
            ExecMode::Client => $this->pauseForClient($run, $call),
            default => $this->failUnsupported($run, $tool, $call),
        };
    }

    /**
     * Execute a MAAC-hosted tool inline and continue the loop (null) on success.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function executeHosted(AgentRun $run, ToolContract $tool, ToolCall $call, array $arguments): ?AgentRun
    {
        if (! $this->hostedTools->has($tool->slug)) {
            $this->failToolCall($call);

            return $this->fail($run, 'hosted_tool_unavailable', "No hosted handler is registered for the tool: {$tool->slug}.");
        }

        try {
            $result = $this->hostedTools->resolve($tool->slug)->handle($arguments);
        } catch (Throwable $exception) {
            $this->failToolCall($call);

            return $this->fail($run, 'hosted_tool_failed', $exception->getMessage());
        }

        $errors = ToolSchema::validatePayload($tool->output_schema, $result);

        if ($errors !== []) {
            $this->failToolCall($call);

            return $this->fail($run, 'hosted_tool_invalid_output', 'The hosted tool returned output that does not satisfy its schema.', ['errors' => $errors]);
        }

        $this->completeToolCall($run, $call, $result);
        $this->tracer->record($run, TraceEventType::ToolResultReceived, "Hosted tool result received: {$tool->slug}.", ['tool_call_id' => $call->id]);
        $this->tracer->record($run, TraceEventType::Validated, 'Tool result validated.');
        $this->appendMessage($run, LlmMessage::tool($tool->slug, (string) json_encode($result)));
        $this->tracer->record($run, TraceEventType::Resumed, 'Run resumed after hosted tool.');

        return null;
    }

    /**
     * Pause the run and wait for the calling application to submit the result.
     */
    private function pauseForClient(AgentRun $run, ToolCall $call): AgentRun
    {
        $this->tracer->record($run, TraceEventType::ToolRequired, 'Run paused for client-side tool.', ['tool_call_id' => $call->id]);
        $run->update(['status' => RunStatus::WaitingForClient]);

        return $run;
    }

    /**
     * Fail the run for an execution mode the runtime does not yet support.
     */
    private function failUnsupported(AgentRun $run, ToolContract $tool, ToolCall $call): AgentRun
    {
        $this->failToolCall($call);

        return $this->fail($run, 'unsupported_execution_mode', "Execution mode [{$tool->execution_mode->value}] is not supported by the runtime yet.");
    }

    /**
     * Validate a submitted client-side tool result before it is accepted.
     *
     * @param  array<string, mixed>  $result
     *
     * @throws RuntimeRequestException
     */
    private function validateClientResult(AgentRun $run, ToolContract $tool, ToolCall $call, array $result): void
    {
        $bytes = strlen((string) json_encode($result));

        if ($bytes > $tool->max_payload_kb * 1024) {
            $this->tracer->record($run, TraceEventType::Failed, 'Submitted tool result exceeds the payload limit.', [
                'code' => 'payload_too_large',
                'tool_call_id' => $call->id,
            ]);

            throw RuntimeRequestException::payloadTooLarge();
        }

        $errors = ToolSchema::validatePayload($tool->output_schema, $result);

        if ($errors !== []) {
            $this->tracer->record($run, TraceEventType::Failed, 'Submitted tool result failed schema validation.', [
                'code' => 'invalid_tool_result',
                'tool_call_id' => $call->id,
                'errors' => $errors,
            ]);

            throw RuntimeRequestException::invalidToolResult($errors);
        }
    }

    /**
     * Persist a pending tool call and record the tool name on the run.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function recordToolCall(AgentRun $run, ToolContract $tool, array $arguments): ToolCall
    {
        $max = $run->toolCalls()->max('sequence');

        $call = $run->toolCalls()->create([
            'tool_contract_id' => $tool->id,
            'tool_name' => $tool->slug,
            'status' => ToolCallStatus::Pending,
            'arguments' => $arguments,
            'execution_mode' => $tool->execution_mode,
            'sequence' => $max === null ? 0 : ((int) $max) + 1,
            'requested_at' => Date::now(),
        ]);

        $tools = $run->tools ?? [];

        if (! in_array($tool->slug, $tools, true)) {
            $tools[] = $tool->slug;
            $run->update(['tools' => $tools]);
        }

        return $call;
    }

    /**
     * Mark a tool call completed, storing a masking-aware copy of its result.
     *
     * @param  array<string, mixed>  $result
     */
    private function completeToolCall(AgentRun $run, ToolCall $call, array $result): void
    {
        $call->update([
            'status' => ToolCallStatus::Completed,
            'result' => $this->redactor->result($run, $result),
            'completed_at' => Date::now(),
        ]);
    }

    /**
     * Mark a tool call failed.
     */
    private function failToolCall(ToolCall $call): void
    {
        $call->update([
            'status' => ToolCallStatus::Failed,
            'completed_at' => Date::now(),
        ]);
    }

    /**
     * Finalize a successful run with the model's final answer.
     */
    private function complete(AgentRun $run, string $text): AgentRun
    {
        $this->appendMessage($run, LlmMessage::assistant($text));
        $run->update([
            'status' => RunStatus::Completed,
            'output' => $text,
            'completed_at' => Date::now(),
            'latency_ms' => $this->latency($run),
        ]);
        $this->tracer->record($run, TraceEventType::Completed, 'Run completed.');

        return $run;
    }

    /**
     * Fail the run with a controlled error code and message.
     *
     * @param  array<string, mixed>  $data
     */
    private function fail(AgentRun $run, string $code, string $message, array $data = []): AgentRun
    {
        $run->update([
            'status' => RunStatus::Failed,
            'error' => $message,
            'failure_reason' => $code,
            'completed_at' => Date::now(),
            'latency_ms' => $this->latency($run),
        ]);
        $this->tracer->record($run, TraceEventType::Failed, $message, [...$data, 'code' => $code]);

        return $run;
    }

    /**
     * Expire a run that ran past its deadline.
     */
    private function expire(AgentRun $run): AgentRun
    {
        $run->update([
            'status' => RunStatus::Expired,
            'error' => 'The run expired before completion.',
            'failure_reason' => 'run_expired',
            'completed_at' => Date::now(),
            'latency_ms' => $this->latency($run),
        ]);
        $this->tracer->record($run, TraceEventType::Failed, 'Run expired.', ['code' => 'run_expired']);

        return $run;
    }

    /**
     * Cancel a run whose agent is no longer published.
     */
    private function cancel(AgentRun $run): AgentRun
    {
        $run->update([
            'status' => RunStatus::Cancelled,
            'error' => 'The run was cancelled because the agent is no longer published.',
            'failure_reason' => 'agent_unpublished',
            'completed_at' => Date::now(),
            'latency_ms' => $this->latency($run),
        ]);
        $this->tracer->record($run, TraceEventType::Failed, 'Run cancelled.', ['code' => 'agent_unpublished']);

        return $run;
    }

    /**
     * Return the run expired when past its deadline, otherwise null.
     */
    private function guardExpiry(AgentRun $run): ?AgentRun
    {
        return $run->hasExpired() ? $this->expire($run) : null;
    }

    /**
     * Accumulate token usage and estimated cost onto the run.
     */
    private function recordUsage(AgentRun $run, LlmUsage $usage, LlmProvider $provider): void
    {
        $cost = ($usage->tokensIn / 1000) * $provider->input_cost
            + ($usage->tokensOut / 1000) * $provider->output_cost;

        $run->update([
            'tokens_in' => $run->tokens_in + $usage->tokensIn,
            'tokens_out' => $run->tokens_out + $usage->tokensOut,
            'cost' => round($run->cost + $cost, 6),
        ]);
    }

    /**
     * Read the conversation history from the run state.
     *
     * @return array<int, LlmMessage>
     */
    private function messages(AgentRun $run): array
    {
        $messages = [];

        foreach ($this->rawState($run, 'messages') as $raw) {
            if (is_array($raw)) {
                $messages[] = LlmMessage::fromArray($raw);
            }
        }

        return $messages;
    }

    /**
     * Append a message to the run state and persist it.
     */
    private function appendMessage(AgentRun $run, LlmMessage $message): void
    {
        $state = $run->state ?? [];
        $messages = $this->rawState($run, 'messages');
        $messages[] = $message->toArray();
        $state['messages'] = $messages;
        $run->update(['state' => $state]);
    }

    /**
     * Get the number of model turns taken so far.
     */
    private function stepsTaken(AgentRun $run): int
    {
        $state = $run->state ?? [];

        return (int) ($state['steps'] ?? 0);
    }

    /**
     * Increment the model-turn counter in the run state.
     */
    private function incrementSteps(AgentRun $run): void
    {
        $state = $run->state ?? [];
        $state['steps'] = $this->stepsTaken($run) + 1;
        $run->update(['state' => $state]);
    }

    /**
     * Read a list value from the run state.
     *
     * @return array<int, mixed>
     */
    private function rawState(AgentRun $run, string $key): array
    {
        $state = $run->state ?? [];
        $value = $state[$key] ?? [];

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * Compute the elapsed run latency in milliseconds.
     */
    private function latency(AgentRun $run): int
    {
        return $run->started_at !== null
            ? (int) abs($run->started_at->diffInMilliseconds(Date::now()))
            : 0;
    }

    /**
     * Maximum number of model/tool iterations a run may take.
     */
    private function maxSteps(): int
    {
        return (int) config('maac.runtime.max_steps');
    }

    /**
     * Wall-clock budget, in seconds, for a run to complete.
     */
    private function timeout(): int
    {
        return (int) config('maac.runtime.default_timeout_seconds');
    }

    /**
     * Per-turn timeout, in seconds, for a single LLM provider call.
     */
    private function turnTimeout(): int
    {
        return (int) config('maac.runtime.per_turn_timeout_seconds');
    }
}
