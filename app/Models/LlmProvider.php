<?php

namespace App\Models;

use App\Enums\LlmStatus;
use App\Enums\Sensitivity;
use Database\Factories\LlmProviderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $team_id
 * @property string $slug
 * @property string $name
 * @property string $code
 * @property string $provider
 * @property string $context_window
 * @property float $input_cost
 * @property float $output_cost
 * @property Sensitivity $sensitivity
 * @property array<int, string> $environments
 * @property LlmStatus $status
 * @property int $usage_pct
 * @property int $runs_count
 * @property string|null $note
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 * @property-read Collection<int, Project> $projects
 * @property-read Collection<int, Agent> $agents
 */
#[Fillable(['team_id', 'slug', 'name', 'code', 'provider', 'context_window', 'input_cost', 'output_cost', 'sensitivity', 'environments', 'status', 'usage_pct', 'runs_count', 'note'])]
class LlmProvider extends Model
{
    /** @use HasFactory<LlmProviderFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the team that owns the model catalog entry.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the projects this model is approved for.
     *
     * @return BelongsToMany<Project, $this>
     */
    public function projects(): BelongsToMany
    {
        return $this->belongsToMany(Project::class, 'project_llm_provider')->withTimestamps();
    }

    /**
     * Get the agents configured to use this model.
     *
     * @return HasMany<Agent, $this>
     */
    public function agents(): HasMany
    {
        return $this->hasMany(Agent::class);
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
            'sensitivity' => Sensitivity::class,
            'status' => LlmStatus::class,
            'environments' => 'array',
            'input_cost' => 'float',
            'output_cost' => 'float',
            'usage_pct' => 'integer',
            'runs_count' => 'integer',
        ];
    }
}
