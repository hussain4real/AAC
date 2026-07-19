<?php

namespace App\Support\Runtime;

use App\Enums\AgentStatus;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\RunMode;
use App\Enums\RunStatus;
use App\Enums\Sensitivity;
use App\Enums\ToolCallStatus;
use App\Enums\TraceEventType;
use App\Enums\WebhookEventType;
use App\Exceptions\Sdk\RuntimeRequestException;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\LlmProvider;
use App\Models\Team;
use App\Models\ToolCall;
use App\Models\ToolContract;
use App\Support\Governance\AgentReadinessGate;
use App\Support\Governance\ApprovalManager;
use App\Support\Governance\RunRedactor;
use App\Support\Governance\RuntimeApprovalPolicy;
use App\Support\Governance\TenantRelationshipGuard;
use App\Support\Runtime\Contracts\LlmRouter;
use App\Support\Runtime\Db\DbToolExecutor;
use App\Support\Runtime\HostedTools\HostedToolRegistry;
use App\Support\Runtime\HostedTools\ProviderHostedToolRegistry;
use App\Support\Runtime\Knowledge\KnowledgeToolExecutor;
use App\Support\Runtime\Mcp\McpToolExecutor;
use App\Support\Runtime\Remote\RemoteHttpToolExecutor;
use App\Support\Runtime\Routing\ModelRouter;
use App\Support\Sdk\RuntimeImplementationRecorder;
use App\Support\Sdk\ToolSchema;
use App\Support\Secrets\Contracts\SecretVault;
use App\Support\Webhooks\RunWebhookEmitter;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Drives the MAACC agent run lifecycle: it creates the run, calls the LLM Router
 * one turn at a time, routes any requested tool by execution mode (executing
 * MAACC-hosted tools inline and pausing for client-side tools), validates every
 * payload at the boundary, and records a trace event for each milestone. The
 * runtime owns this loop precisely so it can pause and resume across requests.
 */
class AgentRunner
{
    /** @var array<string, string> */
    private array $activeClaims = [];

    public function __construct(
        private readonly LlmRouter $router,
        private readonly HostedToolRegistry $hostedTools,
        private readonly ProviderHostedToolRegistry $providerHostedTools,
        private readonly RunTracer $tracer,
        private readonly RunRedactor $redactor,
        private readonly RunWebhookEmitter $webhooks,
        private readonly RemoteHttpToolExecutor $httpTools,
        private readonly McpToolExecutor $connectorTools,
        private readonly KnowledgeToolExecutor $knowledgeTools,
        private readonly DbToolExecutor $dbTools,
        private readonly SecretVault $vault,
        private readonly ModelRouter $modelRouter,
        private readonly RuntimeApprovalPolicy $approvalPolicy,
        private readonly ApprovalManager $approvals,
        private readonly ModelPricing $pricing,
        private readonly AgentPromptComposer $promptComposer,
        private readonly RuntimeImplementationRecorder $runtimeImplementations,
        private readonly TenantRelationshipGuard $relationships,
        private readonly RunStateStore $stateStore,
        private readonly AgentReadinessGate $readiness,
    ) {}

    /**
     * Create and synchronously drive a new run for the given agent and caller
     * context, blocking until it completes, pauses, or fails.
     *
     * @param  array<string, mixed>|null  $callerContext
     */
    public function start(Agent $agent, Application $application, Environment $environment, string $input, ?string $caller, ?array $callerContext = null, bool $testRun = false, ?int $initiatedBy = null): AgentRun
    {
        return $this->process($this->createRun($agent, $application, $environment, $input, $caller, RunMode::Sync, callerContext: $callerContext, testRun: $testRun, initiatedBy: $initiatedBy));
    }

    /**
     * Persist a queued run for the given agent and caller context, recording the
     * request and applying the masking policy, without driving it yet. The
     * synchronous path drives it immediately via {@see self::process()}; the
     * asynchronous path hands the queued run to a worker.
     *
     * @param  array<string, mixed>|null  $callerContext
     */
    public function createRun(Agent $agent, Application $application, Environment $environment, string $input, ?string $caller, RunMode $mode, bool $candidateEvaluation = false, ?array $callerContext = null, bool $testRun = false, ?int $initiatedBy = null, ?string $idempotencyKey = null, ?string $requestHash = null): AgentRun
    {
        if (! $this->relationships->runtimeAgentRelationshipsAreValid($agent, $application, $environment)) {
            throw RuntimeRequestException::invalidRuntimeConfiguration();
        }

        $this->ensureReady($agent, $application, $environment, $candidateEvaluation || $testRun);

        $provider = $agent->llmProvider;
        $snapshot = $this->readiness->executionSnapshot($agent);

        $run = AgentRun::create([
            'agent_id' => $agent->id,
            'agent_version_id' => $testRun ? null : $agent->current_version_id,
            'project_id' => $agent->project_id,
            'application_id' => $application->id,
            'initiated_by' => $initiatedBy,
            'llm_provider_id' => $provider->id,
            'slug' => 'run_'.Str::lower(Str::random(10)),
            'correlation_id' => 'corr_'.Str::lower((string) Str::ulid()),
            'idempotency_key' => $idempotencyKey,
            'request_hash' => $requestHash,
            'policy_version' => (string) config('maacc.runtime.policy_version', '1.0.0'),
            'is_test' => $testRun,
            'execution_snapshot' => $snapshot,
            'caller' => null,
            'caller_context' => $callerContext,
            'mode' => $mode,
            'environment' => $environment,
            'sensitivity' => $this->resolveSensitivity($agent),
            'status' => RunStatus::Queued,
            'tokens_in' => 0,
            'tokens_out' => 0,
            'reserved_tokens' => $agent->max_tokens * $this->maxSteps(),
            'cost' => 0,
            'tools' => [],
            'input' => null,
            'state' => null,
            'started_at' => Date::now(),
            'expires_at' => Date::now()->addSeconds($this->timeout()),
        ]);

        $run->setRelation('agent', $agent);
        $this->stateStore->initialize($run, ['messages' => [LlmMessage::user($input)->toArray()], 'steps' => 0]);

        // Apply the masking policy to the stored prompt (the live conversation
        // state keeps the raw value so the model still receives it).
        $run->update([
            'caller' => $this->redactor->input($run, $caller),
            'input' => $this->redactor->input($run, $input),
            'masked' => $this->redactor->applies($run),
        ]);

        $this->tracer->record($run, TraceEventType::RunRequested, 'Run requested.', ['caller' => $caller, 'mode' => $mode->value]);
        $this->tracer->record($run, TraceEventType::CallerAuthenticated, 'Caller authenticated.', [
            'application' => $application->slug,
            'environment' => $environment->value,
            'subject' => $callerContext['sub'] ?? null,
            'context_version' => $callerContext['v'] ?? null,
        ]);

        return $run;
    }

    /**
     * Select the model, mark the run running, and drive it to its first
     * boundary. Safe to call from a queued worker (it reloads relations).
     */
    public function process(AgentRun $run): AgentRun
    {
        $run->loadMissing(['agent.tools', 'agent.llmProvider', 'agent.routingPolicy', 'llmProvider.vaultSecret', 'application.team']);

        if (! $this->readiness->isReady(
            $run->agent,
            $run->application,
            $run->environment ?? $run->application->environment,
            requirePublished: ! $run->allowsUnpublishedExecution(),
            requireEvaluations: ! $run->allowsUnpublishedExecution(),
            requireImmutableVersion: ! $run->allowsUnpublishedExecution(),
        )) {
            return $this->fail($run, 'agent_not_ready', 'The agent is not ready to execute.');
        }

        if ($run->application->isRuntimeFrozen()) {
            return $this->fail($run, 'runtime_frozen', 'The application runtime is frozen by an incident control.');
        }

        $decision = $this->modelRouter->select($run);

        if (! $decision->provider instanceof LlmProvider) {
            return $this->fail($run, 'model_unavailable', $decision->rationale);
        }

        $this->applyProvider($run, $decision->provider);
        $state = $this->stateStore->get($run);
        $state['routing'] = [
            'chain' => $decision->chainIds(),
            'tried' => [$decision->provider->id],
        ];
        $this->stateStore->put($run, $state);

        $this->tracer->record($run, TraceEventType::ModelSelected, 'Model selected.', $decision->traceData());
        $this->tracer->record($run, TraceEventType::PromptPrepared, 'Prompt prepared.');

        $team = $run->application->team;

        if ($this->approvalPolicy->requires($run, $team)) {
            return $this->pauseForApproval($run, $team);
        }

        $this->markRunning($run);

        return $this->advance($run);
    }

    /**
     * Claim and process a queued run exactly once across duplicate deliveries.
     */
    public function processClaimed(AgentRun $run): AgentRun
    {
        $claimed = $this->claim($run, RunStatus::Queued);

        if ($claimed === null) {
            return $run->fresh();
        }

        try {
            return $this->process($claimed);
        } finally {
            $this->releaseClaim($claimed);
        }
    }

    /**
     * Claim and advance a resumed asynchronous run exactly once.
     */
    public function driveClaimed(AgentRun $run): AgentRun
    {
        $claimed = $this->claim($run, RunStatus::Running);

        if ($claimed === null) {
            return $run->fresh();
        }

        try {
            return $this->drive($claimed);
        } finally {
            $this->releaseClaim($claimed);
        }
    }

    /**
     * Pause a sensitive run for human approval: record the gate, open a governance
     * approval request, and notify any subscribed webhook. A reviewer approves to
     * resume the run or rejects to fail it.
     */
    private function pauseForApproval(AgentRun $run, Team $team): AgentRun
    {
        $run->update(['status' => RunStatus::RequiresApproval]);
        $this->approvals->requestRuntimeApproval($run, $team);
        $this->tracer->record($run, TraceEventType::RequiresApproval, 'Run requires human approval.', [
            'reason' => $this->approvalPolicy->reason($run),
        ]);
        $this->webhooks->emit($run, WebhookEventType::RunRequiresApproval);

        return $run;
    }

    /**
     * Resume a run a reviewer approved: mark it running again so a worker can
     * drive it to completion. No-op if the run is no longer awaiting approval.
     */
    public function approveRuntime(AgentRun $run): void
    {
        if ($run->status !== RunStatus::RequiresApproval) {
            return;
        }

        $run->loadMissing(['agent.tools', 'agent.llmProvider', 'agent.routingPolicy', 'llmProvider.vaultSecret']);
        $this->tracer->record($run, TraceEventType::ApprovalGranted, 'Run approved by a reviewer.');
        $this->markRunning($run);
    }

    /**
     * Fail a run a reviewer rejected. No-op if it is no longer awaiting approval.
     */
    public function denyRuntime(AgentRun $run): AgentRun
    {
        if ($run->status !== RunStatus::RequiresApproval) {
            return $run;
        }

        $this->tracer->record($run, TraceEventType::ApprovalDenied, 'Run denied by a reviewer.');

        return $this->fail($run, 'approval_denied', 'The run was denied by a reviewer.');
    }

    /**
     * Drive a previously-prepared run's model/tool loop to its next boundary.
     * Used to resume a paused run and to continue an async run on a worker.
     */
    public function drive(AgentRun $run): AgentRun
    {
        $run->loadMissing(['agent.tools', 'agent.llmProvider', 'llmProvider.vaultSecret']);

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
     * Resume a paused run with a client-side tool result and drive it
     * synchronously to its next boundary.
     *
     * @param  array<string, mixed>  $result
     *
     * @throws RuntimeRequestException
     */
    public function resume(AgentRun $run, string $toolCallId, array $result): AgentRun
    {
        $accepted = $this->acceptToolResult($run, $toolCallId, $result);

        if ($accepted->status !== RunStatus::Running) {
            return $accepted;
        }

        return $this->advance($accepted);
    }

    /**
     * Validate and accept a client-side tool result for a paused run, marking it
     * running again — without driving it. The synchronous path drives it inline;
     * the asynchronous path hands the running run back to a worker.
     *
     * @param  array<string, mixed>  $result
     *
     * @throws RuntimeRequestException
     */
    public function acceptToolResult(AgentRun $run, string $toolCallId, array $result): AgentRun
    {
        $run->loadMissing(['agent.tools', 'agent.llmProvider', 'llmProvider.vaultSecret', 'application']);

        if (! $this->readiness->isReady(
            $run->agent,
            $run->application,
            $run->environment ?? $run->application->environment,
            requirePublished: ! $run->allowsUnpublishedExecution(),
            requireEvaluations: ! $run->allowsUnpublishedExecution(),
            requireImmutableVersion: ! $run->allowsUnpublishedExecution(),
        )) {
            return $run->agent->status !== AgentStatus::Published && ! $run->allowsUnpublishedExecution()
                ? $this->cancel($run)
                : $this->fail($run, 'agent_not_ready', 'The agent is not ready to execute.');
        }

        if ($run->application->isRuntimeFrozen()) {
            throw RuntimeRequestException::runtimeFrozen();
        }

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
        $result = ToolSchema::projectPayload($tool->output_schema, $result);

        $claimed = AgentRun::query()
            ->whereKey($run->id)
            ->where('status', RunStatus::WaitingForClient->value)
            ->update(['status' => RunStatus::Running->value]);

        if ($claimed !== 1) {
            throw RuntimeRequestException::runNotWaiting();
        }

        $run->setAttribute('status', RunStatus::Running);

        $this->completeToolCall($run, $tool, $call, $result);
        $this->tracer->record($run, TraceEventType::ToolResultReceived, "Client tool result received: {$call->tool_name}.", ['tool_call_id' => $call->id]);
        $this->tracer->record($run, TraceEventType::Validated, 'Tool result validated.');

        // A schema-valid client-tool result proves the application's handler
        // works, so mark its implementation for this environment as validated —
        // real usage keeps the status honest without an explicit SDK report.
        if ($run->environment instanceof Environment) {
            $this->runtimeImplementations->record($tool, $run->application, $run->environment);
        }
        $this->appendMessage($run, LlmMessage::tool($call->tool_name, (string) json_encode($result)));
        $this->tracer->record($run, TraceEventType::Resumed, 'Run resumed after client tool.');

        $this->markRunning($run);

        return $run;
    }

    /**
     * Mark the run running and emit the corresponding webhook event.
     */
    private function markRunning(AgentRun $run): void
    {
        $run->update(['status' => RunStatus::Running]);
        $this->webhooks->emit($run, WebhookEventType::RunRunning);
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

            // External invocations require a published agent; an internal
            // evaluation run is permitted against a candidate that is not yet
            // published so it can be assessed before promotion.
            if ($agent->status !== AgentStatus::Published && ! $run->allowsUnpublishedExecution()) {
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
            if ($this->failover($run, $exception)) {
                return $this->runTurn($run, $agent);
            }

            $this->fail($run, 'model_error', 'The model provider could not complete the request.');

            return null;
        }

        $this->recordUsage($run, $completion->usage, $this->runProvider($run, $agent));
        $this->incrementSteps($run);

        return $completion;
    }

    /**
     * Fail over to the next untried model in the run's routing chain after a model
     * call error. Returns false when no policy fallback remains (so the run fails).
     */
    private function failover(AgentRun $run, Throwable $exception): bool
    {
        $state = $this->stateStore->get($run);
        $routing = $state['routing'] ?? null;

        if (! is_array($routing)) {
            return false;
        }

        $chain = array_values(array_filter($routing['chain'] ?? [], 'is_string'));
        $tried = array_values(array_filter($routing['tried'] ?? [], 'is_string'));

        $next = collect($chain)->first(fn (string $id): bool => ! in_array($id, $tried, true));

        if ($next === null) {
            return false;
        }

        $provider = LlmProvider::find($next);

        if (! $provider instanceof LlmProvider) {
            return false;
        }

        $tried[] = $next;
        $state['routing']['tried'] = $tried;
        $this->stateStore->put($run, $state);
        $this->applyProvider($run, $provider);

        $this->tracer->record($run, TraceEventType::ModelFailover, "Model failover to {$provider->code}.", [
            'model' => $provider->code,
            'reason' => 'provider_call_failed',
            'correlation_id' => $run->correlation_id,
        ]);

        return true;
    }

    /**
     * Record the selected model on the run and bind it as the active provider
     * (with its vault key loaded) for subsequent turns.
     */
    private function applyProvider(AgentRun $run, LlmProvider $provider): void
    {
        if ($run->llm_provider_id !== $provider->id) {
            $run->update(['llm_provider_id' => $provider->id]);
        }

        $run->setRelation('llmProvider', $provider->loadMissing('vaultSecret'));
    }

    /**
     * Build the router request from the run state and the selected model. The
     * provider's API key is resolved from the secrets vault when one is bound, so
     * a central rotation takes effect on the next turn without a redeploy.
     */
    private function buildRequest(AgentRun $run, Agent $agent): LlmRequest
    {
        $provider = $this->runProvider($run, $agent);
        $tools = $this->toolDefinitions($agent);

        return new LlmRequest(
            providerDriver: $provider->driver(),
            modelCode: $provider->code,
            systemPrompt: $this->promptComposer->compose($agent),
            messages: $this->messages($run),
            tools: $tools['runtime'],
            providerTools: $tools['provider'],
            temperature: $agent->temperature,
            maxTokens: $agent->max_tokens,
            timeoutSeconds: $this->turnTimeout(),
            apiKey: $provider->resolveApiKey($this->vault),
            platformOwned: $provider->platform_owned,
        );
    }

    /**
     * Split MAACC-executed tools from hosted tools implemented by the AI provider.
     *
     * @return array{runtime: array<int, LlmToolDefinition>, provider: array<int, LlmProviderToolDefinition>}
     */
    private function toolDefinitions(Agent $agent): array
    {
        $runtime = [];
        $provider = [];

        foreach ($agent->tools as $tool) {
            $providerTool = $this->providerHostedTools->definitionFor($tool);

            if ($providerTool instanceof LlmProviderToolDefinition) {
                $provider[] = $providerTool;

                continue;
            }

            $runtime[] = LlmToolDefinition::fromContract($tool);
        }

        return ['runtime' => $runtime, 'provider' => $provider];
    }

    /**
     * Resolve the model the run executes on — the provider recorded on the run
     * (which advanced routing may have selected), falling back to the agent's
     * configured model.
     */
    private function runProvider(AgentRun $run, Agent $agent): LlmProvider
    {
        return $run->llmProvider ?? $agent->llmProvider;
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

        $errors = ToolSchema::validatePayload($tool->input_schema, $arguments);

        if ($errors !== []) {
            return $this->fail($run, 'invalid_tool_arguments', 'The model produced arguments that do not satisfy the tool input schema.', ['errors' => $errors]);
        }

        if (ToolSchema::payloadBytes($arguments) > $tool->max_payload_kb * 1024) {
            return $this->fail($run, 'tool_arguments_too_large', 'The model produced tool arguments that exceed the contract payload limit.');
        }

        $arguments = ToolSchema::projectPayload($tool->input_schema, $arguments);
        $this->appendMessage($run, LlmMessage::assistant((string) json_encode(['tool' => $tool->slug, 'arguments' => $arguments])));

        $call = $this->recordToolCall($run, $tool, $arguments);
        $this->tracer->record($run, TraceEventType::ToolRequired, "Tool required: {$tool->slug}.", [
            'tool_call_id' => $call->id,
            'execution_mode' => $tool->execution_mode->value,
        ]);

        return match ($tool->execution_mode) {
            ExecMode::Hosted => $this->executeHosted($run, $tool, $call, $arguments),
            ExecMode::Client => $this->pauseForClient($run, $call),
            ExecMode::Http => $this->executeRemoteHttp($run, $tool, $call, $arguments),
            ExecMode::Connector => $this->executeConnector($run, $tool, $call, $arguments),
            ExecMode::Knowledge => $this->executeKnowledge($run, $tool, $call, $arguments),
            ExecMode::Db => $this->executeDb($run, $tool, $call, $arguments),
        };
    }

    /**
     * Execute a MAACC-hosted tool inline and continue the loop (null) on success.
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

        return $this->finishServerTool($run, $tool, $call, $result, 'hosted_tool_invalid_output', 'Hosted');
    }

    /**
     * Execute a remote HTTP tool through the egress-governed executor and continue
     * the loop (null) on success.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function executeRemoteHttp(AgentRun $run, ToolContract $tool, ToolCall $call, array $arguments): ?AgentRun
    {
        if (($blocked = $this->guardToolApproval($run, $tool, $call)) instanceof AgentRun) {
            return $blocked;
        }

        try {
            $result = $this->httpTools->execute($tool, $arguments);
        } catch (ToolExecutionException $exception) {
            $this->failToolCall($call);

            return $this->fail($run, $exception->failureCode, $exception->getMessage());
        }

        return $this->finishServerTool($run, $tool, $call, $result, 'remote_http_invalid_output', 'Remote HTTP', [
            'execution_mode' => ExecMode::Http->value,
            'endpoint_host' => $this->endpointHost($tool),
        ]);
    }

    /**
     * Execute an MCP-backed tool through a registered connector and continue the
     * loop (null) on success.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function executeConnector(AgentRun $run, ToolContract $tool, ToolCall $call, array $arguments): ?AgentRun
    {
        if (($blocked = $this->guardToolApproval($run, $tool, $call)) instanceof AgentRun) {
            return $blocked;
        }

        try {
            $result = $this->connectorTools->execute($tool, $run->environment, $arguments);
        } catch (ToolExecutionException $exception) {
            $this->failToolCall($call);

            return $this->fail($run, $exception->failureCode, $exception->getMessage());
        }

        return $this->finishServerTool($run, $tool, $call, $result, 'connector_invalid_output', 'Connector', [
            'execution_mode' => ExecMode::Connector->value,
            'connector' => $tool->mcpConnector?->slug,
            'remote_tool' => $tool->mcp_tool_name,
        ]);
    }

    /**
     * Execute a knowledge-retrieval (RAG) tool against a governed source and
     * continue the loop (null) on success.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function executeKnowledge(AgentRun $run, ToolContract $tool, ToolCall $call, array $arguments): ?AgentRun
    {
        if (($blocked = $this->guardToolApproval($run, $tool, $call)) instanceof AgentRun) {
            return $blocked;
        }

        try {
            $result = $this->knowledgeTools->execute($tool, $run->environment, $arguments);
        } catch (ToolExecutionException $exception) {
            $this->failToolCall($call);

            return $this->fail($run, $exception->failureCode, $exception->getMessage());
        }

        return $this->finishServerTool($run, $tool, $call, $result, 'knowledge_invalid_output', 'Knowledge', [
            'execution_mode' => ExecMode::Knowledge->value,
            'source' => $tool->knowledgeSource?->slug,
            'citations' => count($result['citations'] ?? []),
        ]);
    }

    /**
     * Execute a governed read-only database (`db`) tool against an approved data
     * source and continue the loop (null) on success.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function executeDb(AgentRun $run, ToolContract $tool, ToolCall $call, array $arguments): ?AgentRun
    {
        if (($blocked = $this->guardToolApproval($run, $tool, $call)) instanceof AgentRun) {
            return $blocked;
        }

        try {
            $result = $this->dbTools->execute($tool, $run->environment, $arguments);
        } catch (ToolExecutionException $exception) {
            $this->failToolCall($call);

            return $this->fail($run, $exception->failureCode, $exception->getMessage());
        }

        return $this->finishServerTool($run, $tool, $call, $result, 'db_invalid_output', 'Database', [
            'execution_mode' => ExecMode::Db->value,
            'source' => $tool->dataSource?->slug,
            'rows' => $result['row_count'] ?? 0,
        ]);
    }

    /**
     * Validate, persist, trace, and feed back a server-side tool result, then
     * continue the loop (null). Shared by hosted, remote HTTP, and connector tools.
     *
     * @param  array<string, mixed>  $result
     * @param  array<string, mixed>  $traceData
     */
    private function finishServerTool(AgentRun $run, ToolContract $tool, ToolCall $call, array $result, string $invalidCode, string $label, array $traceData = []): ?AgentRun
    {
        if (ToolSchema::payloadBytes($result) > $tool->max_payload_kb * 1024) {
            $this->failToolCall($call);

            return $this->fail($run, 'tool_result_too_large', "The {$label} tool returned output that exceeds the contract payload limit.");
        }

        $errors = ToolSchema::validatePayload($tool->output_schema, $result);

        if ($errors !== []) {
            $this->failToolCall($call);

            return $this->fail($run, $invalidCode, "The {$label} tool returned output that does not satisfy its schema.", ['errors' => $errors]);
        }

        $result = ToolSchema::projectPayload($tool->output_schema, $result);
        $this->completeToolCall($run, $tool, $call, $result);
        $this->tracer->record($run, TraceEventType::ToolResultReceived, "{$label} tool result received: {$tool->slug}.", ['tool_call_id' => $call->id, ...$traceData]);
        $this->tracer->record($run, TraceEventType::Validated, 'Tool result validated.');
        $this->appendMessage($run, LlmMessage::tool($tool->slug, (string) json_encode($result)));
        $this->tracer->record($run, TraceEventType::Resumed, "Run resumed after {$label} tool.");

        return null;
    }

    /**
     * Fail a run whose tool requires approval but has not been activated. A
     * server-side egress tool must clear governance before the runtime executes it.
     */
    private function guardToolApproval(AgentRun $run, ToolContract $tool, ToolCall $call): ?AgentRun
    {
        if ($tool->requires_approval && $tool->status !== 'Active') {
            $this->failToolCall($call);

            return $this->fail($run, 'tool_requires_approval', "The tool [{$tool->slug}] requires approval before it can be executed.");
        }

        return null;
    }

    /**
     * Resolve the host of a remote HTTP tool's endpoint for trace context.
     */
    private function endpointHost(ToolContract $tool): ?string
    {
        $endpoint = $tool->httpConfig()['endpoint'] ?? null;

        return is_string($endpoint) ? (parse_url($endpoint, PHP_URL_HOST) ?: null) : null;
    }

    /**
     * Pause the run and wait for the calling application to submit the result.
     */
    private function pauseForClient(AgentRun $run, ToolCall $call): AgentRun
    {
        $this->tracer->record($run, TraceEventType::ToolRequired, 'Run paused for client-side tool.', ['tool_call_id' => $call->id]);
        $run->update(['status' => RunStatus::WaitingForClient]);
        $this->webhooks->emit($run, WebhookEventType::RunToolRequested);

        return $run;
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
        $bytes = ToolSchema::payloadBytes($result);

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
        $call = DB::transaction(function () use ($run, $tool, $arguments): ToolCall {
            $locked = AgentRun::query()->lockForUpdate()->findOrFail($run->id);
            $sequence = $locked->next_tool_sequence;
            $tools = $locked->tools ?? [];

            if (! in_array($tool->slug, $tools, true)) {
                $tools[] = $tool->slug;
            }

            $locked->update([
                'next_tool_sequence' => $sequence + 1,
                'tools' => $tools,
            ]);
            $run->forceFill(['next_tool_sequence' => $sequence + 1, 'tools' => $tools]);

            return ToolCall::query()->create([
                'agent_run_id' => $run->id,
                'tool_contract_id' => $tool->id,
                'tool_name' => $tool->slug,
                'status' => ToolCallStatus::Pending,
                'arguments' => $this->redactor->arguments($run, $arguments),
                'execution_mode' => $tool->execution_mode,
                'sequence' => $sequence,
                'requested_at' => Date::now(),
            ]);
        }, 3);
        $this->stateStore->putToolArguments($run, $call->id, $arguments);

        return $call;
    }

    /**
     * Mark a tool call completed, storing a masking-aware copy of its result.
     *
     * @param  array<string, mixed>  $result
     */
    private function completeToolCall(AgentRun $run, ToolContract $tool, ToolCall $call, array $result): void
    {
        $call->update([
            'status' => ToolCallStatus::Completed,
            'result' => $this->redactResult($run, $tool, $result),
            'completed_at' => Date::now(),
        ]);
        $this->stateStore->forgetToolArguments($run, $call->id);
    }

    /**
     * Apply the team masking policy and the tool's field-level redaction rules to
     * a tool result before it is stored. The live conversation state keeps the raw
     * value so the model still reasons over it; only the at-rest copy is redacted.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    private function redactResult(AgentRun $run, ToolContract $tool, array $result): array
    {
        $masked = $this->redactor->result($run, $result) ?? [];

        foreach ($tool->redactionPaths() as $path) {
            if (Arr::has($masked, $path)) {
                Arr::set($masked, $path, '[redacted]');
            }
        }

        return $masked;
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
        if (! $this->claimTerminal($run, RunStatus::Completed, [
            'status' => RunStatus::Completed,
            'reserved_tokens' => 0,
            'output' => $this->redactor->output($run, $text),
            'completed_at' => Date::now(),
            'latency_ms' => $this->latency($run),
        ])) {
            return $run->fresh();
        }
        $this->stateStore->forget($run);
        $this->tracer->record($run, TraceEventType::Completed, 'Run completed.');
        $this->webhooks->emit($run, WebhookEventType::RunCompleted);

        return $run;
    }

    /**
     * Fail the run with a controlled error code and message.
     *
     * @param  array<string, mixed>  $data
     */
    private function fail(AgentRun $run, string $code, string $message, array $data = []): AgentRun
    {
        $publicMessage = $this->publicFailureMessage($code);

        if (! $this->claimTerminal($run, RunStatus::Failed, [
            'status' => RunStatus::Failed,
            'reserved_tokens' => 0,
            'error' => $publicMessage,
            'failure_reason' => $code,
            'completed_at' => Date::now(),
            'latency_ms' => $this->latency($run),
        ])) {
            return $run->fresh();
        }
        $this->stateStore->forget($run);
        $this->tracer->record($run, TraceEventType::Failed, $publicMessage, [
            'code' => $code,
            'correlation_id' => $run->correlation_id,
        ]);
        $this->webhooks->emit($run, WebhookEventType::RunFailed);

        return $run;
    }

    /**
     * Expire a run that ran past its deadline.
     */
    private function expire(AgentRun $run): AgentRun
    {
        if (! $this->claimTerminal($run, RunStatus::Expired, [
            'status' => RunStatus::Expired,
            'reserved_tokens' => 0,
            'error' => 'The run expired before completion.',
            'failure_reason' => 'run_expired',
            'completed_at' => Date::now(),
            'latency_ms' => $this->latency($run),
        ])) {
            return $run->fresh();
        }
        $this->stateStore->forget($run);
        $this->tracer->record($run, TraceEventType::Failed, 'Run expired.', [
            'code' => 'run_expired',
            'correlation_id' => $run->correlation_id,
        ]);
        $this->webhooks->emit($run, WebhookEventType::RunExpired);

        return $run;
    }

    /**
     * Cancel a run whose agent is no longer published.
     */
    private function cancel(AgentRun $run): AgentRun
    {
        if (! $this->claimTerminal($run, RunStatus::Cancelled, [
            'status' => RunStatus::Cancelled,
            'reserved_tokens' => 0,
            'error' => 'The run was cancelled because the agent is no longer published.',
            'failure_reason' => 'agent_unpublished',
            'completed_at' => Date::now(),
            'latency_ms' => $this->latency($run),
        ])) {
            return $run->fresh();
        }
        $this->stateStore->forget($run);
        $this->tracer->record($run, TraceEventType::Failed, 'Run cancelled.', [
            'code' => 'agent_unpublished',
            'correlation_id' => $run->correlation_id,
        ]);
        $this->webhooks->emit($run, WebhookEventType::RunCancelled);

        return $run;
    }

    /**
     * Atomically win a terminal transition. Only the winner records terminal
     * trace/webhook effects, preventing duplicate jobs from emitting twice.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function claimTerminal(AgentRun $run, RunStatus $status, array $attributes): bool
    {
        $updated = AgentRun::query()
            ->whereKey($run->id)
            ->whereNotIn('status', array_map(
                static fn (RunStatus $terminal): string => $terminal->value,
                RunStatus::terminalCases(),
            ))
            ->update([...$attributes, 'status' => $status->value, 'terminal_event_emitted' => true]);

        if ($updated !== 1) {
            return false;
        }

        $run->forceFill([...$attributes, 'status' => $status, 'terminal_event_emitted' => true]);

        return true;
    }

    /**
     * Return the run expired when past its deadline, otherwise null.
     */
    private function guardExpiry(AgentRun $run): ?AgentRun
    {
        return $run->hasExpired() ? $this->expire($run) : null;
    }

    /**
     * Accumulate token usage and estimated cost onto the run. Cost is an estimate
     * (token usage × the reviewed per-1M price catalog), since no provider returns
     * a per-request dollar amount through its API.
     */
    private function recordUsage(AgentRun $run, LlmUsage $usage, LlmProvider $provider): void
    {
        $cost = $this->pricing->estimate($provider, $usage->tokensIn, $usage->tokensOut);

        $run->update([
            'tokens_in' => $run->tokens_in + $usage->tokensIn,
            'tokens_out' => $run->tokens_out + $usage->tokensOut,
            'reserved_tokens' => max(0, $run->reserved_tokens - $usage->tokensIn - $usage->tokensOut),
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
        $state = $this->stateStore->get($run);
        $messages = $this->rawState($run, 'messages');
        $messages[] = $message->toArray();
        $state['messages'] = $messages;
        $this->stateStore->put($run, $state);
    }

    /**
     * Get the number of model turns taken so far.
     */
    private function stepsTaken(AgentRun $run): int
    {
        $state = $this->stateStore->get($run);

        return (int) ($state['steps'] ?? 0);
    }

    /**
     * Increment the model-turn counter in the run state.
     */
    private function incrementSteps(AgentRun $run): void
    {
        $state = $this->stateStore->get($run);
        $state['steps'] = $this->stepsTaken($run) + 1;
        $this->stateStore->put($run, $state);
    }

    /**
     * Read a list value from the run state.
     *
     * @return array<int, mixed>
     */
    private function rawState(AgentRun $run, string $key): array
    {
        $state = $this->stateStore->get($run);
        $value = $state[$key] ?? [];

        return is_array($value) ? array_values($value) : [];
    }

    /**
     * Enforce the shared readiness decision before creating any run.
     */
    private function ensureReady(Agent $agent, Application $application, Environment $environment, bool $candidateEvaluation): void
    {
        if (! $this->readiness->isReady(
            $agent,
            $application,
            $environment,
            requirePublished: ! $candidateEvaluation,
            requireEvaluations: ! $candidateEvaluation,
            requireImmutableVersion: ! $candidateEvaluation,
        )) {
            throw RuntimeRequestException::invalidRuntimeConfiguration();
        }
    }

    /**
     * Atomically claim a run in the expected state. A stale claim can be taken
     * over after the queue visibility timeout so crashed workers are repairable.
     */
    private function claim(AgentRun $run, RunStatus $expected): ?AgentRun
    {
        return DB::transaction(function () use ($run, $expected): ?AgentRun {
            $locked = AgentRun::query()->lockForUpdate()->findOrFail($run->id);
            $staleBefore = Date::now()->subSeconds((int) config('queue.connections.database.retry_after', 180));

            if ($locked->status !== $expected
                || ($locked->processing_token !== null && $locked->processing_claimed_at?->isAfter($staleBefore))) {
                return null;
            }

            $token = (string) Str::uuid();
            $locked->update([
                'processing_token' => $token,
                'processing_claimed_at' => Date::now(),
            ]);
            $this->activeClaims[$locked->id] = $token;

            return $locked;
        }, 3);
    }

    private function releaseClaim(AgentRun $run): void
    {
        $token = $this->activeClaims[$run->id] ?? null;

        if (! is_string($token)) {
            return;
        }

        unset($this->activeClaims[$run->id]);

        AgentRun::query()
            ->whereKey($run->id)
            ->where('processing_token', $token)
            ->update(['processing_token' => null, 'processing_claimed_at' => null]);
    }

    /**
     * Return a stable, non-sensitive public message for a runtime failure code.
     */
    private function publicFailureMessage(string $code): string
    {
        return match ($code) {
            'runtime_frozen' => 'The application runtime is currently unavailable.',
            'approval_denied' => 'The run was denied by a reviewer.',
            'model_unavailable', 'model_error' => 'The model provider could not complete this run.',
            'step_limit_exceeded' => 'The run reached its configured step limit.',
            'unknown_tool', 'invalid_tool_arguments', 'hosted_tool_unavailable', 'hosted_tool_failed',
            'hosted_tool_invalid_output', 'remote_http_invalid_output', 'connector_invalid_output',
            'knowledge_invalid_output', 'db_invalid_output', 'tool_requires_approval' => 'A requested tool could not be executed.',
            default => 'The run could not be completed.',
        };
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
        return (int) config('maacc.runtime.max_steps');
    }

    /**
     * Wall-clock budget, in seconds, for a run to complete.
     */
    private function timeout(): int
    {
        return (int) config('maacc.runtime.default_timeout_seconds');
    }

    /**
     * Per-turn timeout, in seconds, for a single LLM provider call.
     */
    private function turnTimeout(): int
    {
        return (int) config('maacc.runtime.per_turn_timeout_seconds');
    }
}
