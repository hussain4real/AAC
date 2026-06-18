<?php

namespace App\Models;

use App\Concerns\RecordsAuditEvents;
use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Enums\Sensitivity;
use App\Enums\ToolScope;
use App\Support\Sdk\ToolCompatibility;
use Database\Factories\ToolContractFactory;
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
 * @property int $team_id
 * @property string|null $application_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property ToolScope $scope
 * @property ExecMode $execution_mode
 * @property Sensitivity $sensitivity
 * @property bool $requires_approval
 * @property string $status
 * @property ImplStatus $implementation_status
 * @property int $timeout_seconds
 * @property int $max_payload_kb
 * @property array<string, string> $input_schema
 * @property array<string, string> $output_schema
 * @property string $version
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Application|null $application
 * @property-read Collection<int, ToolAssignment> $assignments
 * @property-read Collection<int, ToolImplementation> $implementations
 * @property-read Collection<int, Agent> $agents
 * @property-read Collection<int, ToolCall> $toolCalls
 */
#[Fillable(['team_id', 'application_id', 'slug', 'name', 'description', 'scope', 'execution_mode', 'sensitivity', 'requires_approval', 'status', 'implementation_status', 'timeout_seconds', 'max_payload_kb', 'input_schema', 'output_schema', 'version'])]
class ToolContract extends Model
{
    /** @use HasFactory<ToolContractFactory> */
    use HasFactory, HasUuids, RecordsAuditEvents, SoftDeletes;

    /**
     * Get the team that owns the tool contract.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the application that owns the tool (null for global/platform tools).
     *
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the tool's scope assignments.
     *
     * @return HasMany<ToolAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(ToolAssignment::class);
    }

    /**
     * Get the per-application implementation records for the tool.
     *
     * @return HasMany<ToolImplementation, $this>
     */
    public function implementations(): HasMany
    {
        return $this->hasMany(ToolImplementation::class);
    }

    /**
     * Get the agents that have this tool assigned directly.
     *
     * @return BelongsToMany<Agent, $this>
     */
    public function agents(): BelongsToMany
    {
        return $this->belongsToMany(Agent::class, 'tool_assignments', 'tool_contract_id', 'agent_id')
            ->withTimestamps();
    }

    /**
     * Get the calls made against the tool during runs.
     *
     * @return HasMany<ToolCall, $this>
     */
    public function toolCalls(): HasMany
    {
        return $this->hasMany(ToolCall::class);
    }

    /**
     * Get the owner label shown in the console ("Platform" for global tools).
     */
    public function ownerLabel(): string
    {
        return $this->application_id === null ? 'Platform' : $this->application->slug;
    }

    /**
     * Compute a stable fingerprint of the contract's input/output schema shape,
     * used for SDK implementation compatibility checks.
     */
    public function schemaFingerprint(): string
    {
        return ToolCompatibility::fingerprint($this->input_schema, $this->output_schema);
    }

    /**
     * Determine whether the tool is executed by the calling application via the
     * SDK (and therefore requires a reported client-side implementation).
     */
    public function isClientSide(): bool
    {
        return $this->execution_mode->isClientSide();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Resolve the team this tool contract is audited under.
     */
    protected function auditTeam(): ?Team
    {
        return $this->team;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope' => ToolScope::class,
            'execution_mode' => ExecMode::class,
            'sensitivity' => Sensitivity::class,
            'requires_approval' => 'boolean',
            'implementation_status' => ImplStatus::class,
            'timeout_seconds' => 'integer',
            'max_payload_kb' => 'integer',
            'input_schema' => 'array',
            'output_schema' => 'array',
        ];
    }
}
