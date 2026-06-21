<?php

namespace App\Models;

use App\Enums\Environment;
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
 * @property string $project_id
 * @property string $application_id
 * @property string|null $llm_provider_id
 * @property string $slug
 * @property string|null $caller
 * @property Environment|null $environment
 * @property Sensitivity $sensitivity
 * @property RunStatus $status
 * @property int $tokens_in
 * @property int $tokens_out
 * @property float $cost
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
 * @property-read Project $project
 * @property-read Application $application
 * @property-read LlmProvider|null $llmProvider
 * @property-read Collection<int, ToolCall> $toolCalls
 * @property-read Collection<int, TraceEvent> $traceEvents
 */
#[Fillable(['agent_id', 'project_id', 'application_id', 'llm_provider_id', 'slug', 'caller', 'environment', 'sensitivity', 'status', 'tokens_in', 'tokens_out', 'cost', 'latency_ms', 'tools', 'input', 'output', 'state', 'error', 'failure_reason', 'masked', 'started_at', 'completed_at', 'expires_at'])]
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
            'environment' => Environment::class,
            'sensitivity' => Sensitivity::class,
            'status' => RunStatus::class,
            'masked' => 'boolean',
            'tokens_in' => 'integer',
            'tokens_out' => 'integer',
            'cost' => 'float',
            'latency_ms' => 'integer',
            'tools' => 'array',
            'state' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }
}
