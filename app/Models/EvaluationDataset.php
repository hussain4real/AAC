<?php

namespace App\Models;

use App\Concerns\RecordsAuditEvents;
use Database\Factories\EvaluationDatasetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * A golden dataset: a named, reusable set of evaluation cases that exercise an
 * agent's behavior across the runtime surface (no-tool, client-tool,
 * remote-tool, connector, and RAG workflows). Owned by a team and optionally
 * scoped to a project.
 *
 * @property string $id
 * @property int $team_id
 * @property string|null $project_id
 * @property string $slug
 * @property string $name
 * @property string|null $description
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Project|null $project
 * @property-read User|null $creator
 * @property-read Collection<int, EvaluationCase> $cases
 * @property-read Collection<int, Evaluation> $evaluations
 */
#[Fillable(['team_id', 'project_id', 'slug', 'name', 'description', 'created_by'])]
class EvaluationDataset extends Model
{
    /** @use HasFactory<EvaluationDatasetFactory> */
    use HasFactory, HasUuids, RecordsAuditEvents, SoftDeletes;

    /**
     * Get the team that owns the dataset.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the project the dataset is scoped to (null for team-wide datasets).
     *
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the user that created the dataset.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the dataset's cases, ordered for stable execution.
     *
     * @return HasMany<EvaluationCase, $this>
     */
    public function cases(): HasMany
    {
        return $this->hasMany(EvaluationCase::class)->orderBy('ordinal')->orderBy('created_at');
    }

    /**
     * Get the evaluation runs produced from the dataset.
     *
     * @return HasMany<Evaluation, $this>
     */
    public function evaluations(): HasMany
    {
        return $this->hasMany(Evaluation::class);
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Resolve the team this dataset is audited under.
     */
    protected function auditTeam(): ?Team
    {
        return $this->team;
    }
}
