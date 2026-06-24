<?php

namespace App\Models;

use App\Concerns\RecordsAuditEvents;
use App\Enums\Environment;
use App\Enums\EvaluationStatus;
use Database\Factories\EvaluationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A run of a golden dataset against an agent. It snapshots the agent's version,
 * model, and prompt fingerprint (so comparison reports can attribute a behavior
 * change), records rolled-up outcome metrics, and — when `is_required` — gates
 * promotion: the agent cannot be published while its latest required evaluation
 * has not passed.
 *
 * @property string $id
 * @property int $team_id
 * @property string $evaluation_dataset_id
 * @property string $agent_id
 * @property string|null $agent_version_id
 * @property Environment $environment
 * @property string $label
 * @property EvaluationStatus $status
 * @property bool $is_required
 * @property string $agent_version
 * @property string|null $model_code
 * @property string|null $prompt_fingerprint
 * @property int $cases_total
 * @property int $cases_passed
 * @property float $pass_rate
 * @property float $total_cost
 * @property int $avg_latency_ms
 * @property float $correctness_rate
 * @property float $safety_rate
 * @property float $citation_rate
 * @property Carbon|null $started_at
 * @property Carbon|null $completed_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read EvaluationDataset $dataset
 * @property-read Agent $agent
 * @property-read AgentVersion|null $agentVersion
 * @property-read User|null $creator
 * @property-read Collection<int, EvaluationResult> $results
 */
#[Fillable(['team_id', 'evaluation_dataset_id', 'agent_id', 'agent_version_id', 'environment', 'label', 'status', 'is_required', 'agent_version', 'model_code', 'prompt_fingerprint', 'cases_total', 'cases_passed', 'pass_rate', 'total_cost', 'avg_latency_ms', 'correctness_rate', 'safety_rate', 'citation_rate', 'started_at', 'completed_at', 'created_by'])]
class Evaluation extends Model
{
    /** @use HasFactory<EvaluationFactory> */
    use HasFactory, HasUuids, RecordsAuditEvents;

    /**
     * Get the team that owns the evaluation.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the dataset the evaluation ran.
     *
     * @return BelongsTo<EvaluationDataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(EvaluationDataset::class, 'evaluation_dataset_id');
    }

    /**
     * Get the agent the evaluation ran against.
     *
     * @return BelongsTo<Agent, $this>
     */
    public function agent(): BelongsTo
    {
        return $this->belongsTo(Agent::class);
    }

    /**
     * Get the agent version snapshot the evaluation ran against, if pinned.
     *
     * @return BelongsTo<AgentVersion, $this>
     */
    public function agentVersion(): BelongsTo
    {
        return $this->belongsTo(AgentVersion::class, 'agent_version_id');
    }

    /**
     * Get the user that started the evaluation.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the per-case results of the evaluation.
     *
     * @return HasMany<EvaluationResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(EvaluationResult::class);
    }

    /**
     * Whether the evaluation met every required case assertion.
     */
    public function hasPassed(): bool
    {
        return $this->status->hasPassed();
    }

    /**
     * Scope to evaluations flagged as a promotion-gating requirement.
     *
     * @param  Builder<Evaluation>  $query
     */
    public function scopeRequired(Builder $query): void
    {
        $query->where('is_required', true);
    }

    /**
     * Resolve the team this evaluation is audited under.
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
            'environment' => Environment::class,
            'status' => EvaluationStatus::class,
            'is_required' => 'boolean',
            'cases_total' => 'integer',
            'cases_passed' => 'integer',
            'pass_rate' => 'float',
            'total_cost' => 'float',
            'avg_latency_ms' => 'integer',
            'correctness_rate' => 'float',
            'safety_rate' => 'float',
            'citation_rate' => 'float',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
