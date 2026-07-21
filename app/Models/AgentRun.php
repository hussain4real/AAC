<?php

namespace App\Models;

use App\Enums\Environment;
use App\Enums\RunMode;
use App\Enums\RunStatus;
use App\Enums\Sensitivity;
use App\Enums\ToolCallStatus;
use Database\Factories\AgentRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $agent_id
 * @property string|null $agent_version_id
 * @property string|null $evaluation_id
 * @property string $project_id
 * @property string $application_id
 * @property int|null $initiated_by
 * @property string|null $llm_provider_id
 * @property string $slug
 * @property string|null $correlation_id
 * @property string|null $idempotency_key
 * @property string|null $request_hash
 * @property string $policy_version
 * @property bool $is_test
 * @property array<string, mixed>|null $execution_snapshot
 * @property string|null $caller
 * @property array<string, mixed>|null $caller_context
 * @property string|null $caller_subject
 * @property string|null $caller_department
 * @property RunMode $mode
 * @property Environment|null $environment
 * @property Sensitivity $sensitivity
 * @property RunStatus $status
 * @property int $tokens_in
 * @property int $tokens_out
 * @property int $reserved_tokens
 * @property int $next_trace_sequence
 * @property int $next_tool_sequence
 * @property string|null $processing_token
 * @property Carbon|null $processing_claimed_at
 * @property bool $terminal_event_emitted
 * @property float $cost
 * @property string $cost_currency
 * @property string|null $pricing_source
 * @property string|null $pricing_version
 * @property Carbon|null $pricing_effective_at
 * @property int|null $latency_ms
 * @property array<int, string>|null $tools
 * @property string|null $input
 * @property string|null $output
 * @property array<string, mixed>|null $state
 * @property string|null $error
 * @property string|null $failure_reason
 * @property bool $masked
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property Carbon|null $expires_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Agent $agent
 * @property-read AgentVersion|null $agentVersion
 * @property-read Evaluation|null $evaluation
 * @property-read Project $project
 * @property-read Application $application
 * @property-read User|null $initiator
 * @property-read LlmProvider|null $llmProvider
 * @property-read Collection<int, ToolCall> $toolCalls
 * @property-read Collection<int, TraceEvent> $traceEvents
 */
#[Fillable(['agent_id', 'agent_version_id', 'evaluation_id', 'project_id', 'application_id', 'initiated_by', 'llm_provider_id', 'slug', 'correlation_id', 'idempotency_key', 'request_hash', 'policy_version', 'is_test', 'execution_snapshot', 'caller', 'caller_context', 'caller_subject', 'caller_department', 'mode', 'environment', 'sensitivity', 'status', 'tokens_in', 'tokens_out', 'reserved_tokens', 'next_trace_sequence', 'next_tool_sequence', 'processing_token', 'processing_claimed_at', 'terminal_event_emitted', 'cost', 'cost_currency', 'pricing_source', 'pricing_version', 'pricing_effective_at', 'latency_ms', 'tools', 'input', 'output', 'state', 'error', 'failure_reason', 'masked', 'started_at', 'completed_at', 'expires_at'])]
class AgentRun extends Model
{
    /** @use HasFactory<AgentRunFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the agent that produced the run.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get the immutable published agent version used for the run, when one exists.
     *
     * @return BelongsTo<AgentVersion, $this>
     */
    public function agentVersion(): BelongsTo
    {
        return $this->belongsTo(AgentVersion::class);
    }

    /**
     * Get the evaluation that produced the run (null for normal runs).
     *
     * @return BelongsTo<Evaluation, $this>
     */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    /**
     * Get the project the run belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the application that invoked the run.
     *
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the console user that initiated a governed test run, when applicable.
     *
     * @return BelongsTo<User, $this>
     */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    /**
     * Get the LLM model used for the run.
     *
     * @return BelongsTo<LlmProvider, $this>
     */
    public function llmProvider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class);
    }

    /**
     * Get the tool calls made during the run.
     *
     * @return HasMany<ToolCall, $this>
     */
    public function toolCalls(): HasMany
    {
        return $this->hasMany(ToolCall::class);
    }

    /**
     * Get the trace events recorded during the run.
     *
     * @return HasMany<TraceEvent, $this>
     */
    public function traceEvents(): HasMany
    {
        return $this->hasMany(TraceEvent::class);
    }

    /**
     * Get the tool call the run is currently paused on, if any.
     *
     * @return HasMany<ToolCall, $this>
     */
    public function pendingToolCalls(): HasMany
    {
        return $this->toolCalls()->where('status', ToolCallStatus::Pending);
    }

    /**
     * Determine whether the run is paused waiting for a client-side tool result.
     */
    public function isWaitingForClient(): bool
    {
        return $this->status === RunStatus::WaitingForClient;
    }

    /**
     * Determine whether the run is driven asynchronously by a queued worker.
     */
    public function isAsync(): bool
    {
        return $this->mode === RunMode::Async;
    }

    /**
     * Determine whether the run is an internal evaluation run (which the runtime
     * permits against an agent that is not yet published).
     */
    public function isEvaluation(): bool
    {
        return $this->evaluation_id !== null;
    }

    /**
     * Whether this run may execute an unpublished candidate through an internal,
     * authorized evaluation or console test path.
     */
    public function allowsUnpublishedExecution(): bool
    {
        return $this->isEvaluation() || $this->is_test;
    }

    /**
     * Determine whether the run has passed its expiry deadline without finishing.
     */
    public function hasExpired(): bool
    {
        return ! $this->status->isTerminal()
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => RunMode::class,
            'environment' => Environment::class,
            'sensitivity' => Sensitivity::class,
            'status' => RunStatus::class,
            'masked' => 'boolean',
            'is_test' => 'boolean',
            'execution_snapshot' => 'array',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'reserved_tokens' => 'integer',
            'next_trace_sequence' => 'integer',
            'next_tool_sequence' => 'integer',
            'processing_claimed_at' => 'datetime',
            'terminal_event_emitted' => 'boolean',
            'cost' => 'float',
            'pricing_effective_at' => 'datetime',
            'latency_ms' => 'integer',
            'tools' => 'array',
            'caller_context' => 'array',
            'state' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
