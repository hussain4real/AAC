<?php

namespace App\Models;

use App\Concerns\RecordsAuditEvents;
use App\Enums\Environment;
use App\Enums\ProjectStatus;
use Database\Factories\ProjectFactory;
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
 * @property string $application_id
 * @property string $slug
 * @property string $name
 * @property Environment $environment
 * @property string|null $description
 * @property string|null $business_owner
 * @property string|null $technical_owner
 * @property ProjectStatus $status
 * @property int $agents_count
 * @property int $tools_count
 * @property int $runs_7d
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Application $application
 * @property-read Collection<int, LlmProvider> $llmProviders
 * @property-read Collection<int, Agent> $agents
 * @property-read Collection<int, User> $members
 * @property-read Collection<int, ProjectMember> $projectMembers
 * @property-read Collection<int, EvaluationDataset> $evaluationDatasets
 */
#[Fillable(['application_id', 'slug', 'name', 'environment', 'description', 'business_owner', 'technical_owner', 'status', 'agents_count', 'tools_count', 'runs_7d'])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, HasUuids, RecordsAuditEvents, SoftDeletes;

    /**
     * Get the application the project belongs to.
     *
     * @return BelongsTo<Application, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /**
     * Get the LLM models approved for this project.
     *
     * @return BelongsToMany<LlmProvider, $this>
     */
    public function llmProviders(): BelongsToMany
    {
        return $this->belongsToMany(LlmProvider::class, 'project_llm_provider')->withTimestamps();
    }

    /**
     * Get the agents created under the project.
     *
     * @return HasMany<Agent, $this>
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
    }

    /**
     * Get the project's member records (with their MAAC role).
     *
     * @return HasMany<ProjectMember, $this>
     */
    public function projectMembers(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * Get the golden evaluation datasets scoped to the project.
     *
     * @return HasMany<EvaluationDataset, $this>
     */
    public function evaluationDatasets(): HasMany
    {
        return $this->hasMany(EvaluationDataset::class);
    }

    /**
     * Get the users assigned to the project.
     *
     * @return BelongsToMany<User, $this, ProjectMember, 'pivot'>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->using(ProjectMember::class)
            ->withPivot(['maac_role'])
            ->withTimestamps();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Resolve the team this project is audited under.
     */
    protected function auditTeam(): ?Team
    {
        return $this->application->team;
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
            'status' => ProjectStatus::class,
            'agents_count' => 'integer',
            'tools_count' => 'integer',
            'runs_7d' => 'integer',
        ];
    }
}
