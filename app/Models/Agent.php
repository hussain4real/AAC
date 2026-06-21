<?php

namespace App\Models;

use App\Concerns\RecordsAuditEvents;
use App\Enums\AgentStatus;
use App\Enums\Sensitivity;
use Database\Factories\AgentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $project_id
 * @property string $llm_provider_id
 * @property string|null $current_version_id
 * @property string $slug
 * @property string $agent_slug
 * @property string $name
 * @property string $version
 * @property AgentStatus $status
 * @property Sensitivity $sensitivity
 * @property string $system_prompt
 * @property float $temperature
 * @property int $max_tokens
 * @property string|null $description
 * @property float $success_rate
 * @property int $runs_7d
 * @property Carbon|null $last_run_at
 * @property Carbon|null $published_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Project $project
 * @property-read LlmProvider $llmProvider
 * @property-read AgentVersion|null $currentVersion
 * @property-read Collection<int, AgentVersion> $versions
 * @property-read Collection<int, ToolContract> $tools
 * @property-read Collection<int, AgentRun> $runs
 */
#[Fillable(['project_id', 'llm_provider_id', 'current_version_id', 'slug', 'agent_slug', 'name', 'version', 'status', 'sensitivity', 'system_prompt', 'temperature', 'max_tokens', 'description', 'success_rate', 'runs_7d', 'last_run_at', 'published_at'])]
class Agent extends Model
{
    /** @use HasFactory<AgentFactory> */
    use HasFactory, HasUuids, RecordsAuditEvents, SoftDeletes;

    /**
     * Get the project the agent belongs to.
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the LLM model the agent is configured to use.
     *
     * @return BelongsTo<LlmProvider, $this>
     */
    public function llmProvider(): BelongsTo
    {
        return $this->belongsTo(LlmProvider::class);
    }

    /**
     * Get the currently published version of the agent.
     *
     * @return BelongsTo<AgentVersion, $this>
     */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(AgentVersion::class, 'current_version_id');
    }

    /**
     * Get all versions of the agent.
     *
     * @return HasMany<AgentVersion, $this>
     */
    public function versions(): HasMany
    {
        return $this->hasMany(AgentVersion::class);
    }

    /**
     * Get the tools assigned directly to this agent.
     *
     * @return BelongsToMany<ToolContract, $this>
     */
    public function tools(): BelongsToMany
    {
        return $this->belongsToMany(ToolContract::class, 'tool_assignments', 'agent_id', 'tool_contract_id')
            ->withTimestamps();
    }

    /**
     * Get the runs recorded for the agent.
     *
     * @return HasMany<AgentRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }

    /**
     * Get the application the agent belongs to (through its project).
     */
    public function application(): ?Application
    {
        return $this->project->application;
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Resolve the team this agent is audited under.
     */
    protected function auditTeam(): ?Team
    {
        return $this->project->application->team;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AgentStatus::class,
            'sensitivity' => Sensitivity::class,
            'temperature' => 'float',
            'max_tokens' => 'integer',
            'success_rate' => 'float',
            'runs_7d' => 'integer',
            'last_run_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
