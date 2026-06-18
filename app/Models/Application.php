<?php

namespace App\Models;

use App\Concerns\RecordsAuditEvents;
use App\Enums\AppStatus;
use App\Enums\Environment;
use Carbon\CarbonInterface;
use Database\Factories\ApplicationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $team_id
 * @property string $slug
 * @property string $code
 * @property string $name
 * @property string $department
 * @property string $owner_name
 * @property string $owner_email
 * @property Environment $environment
 * @property AppStatus $status
 * @property string|null $stack
 * @property string|null $description
 * @property string|null $region
 * @property Carbon|null $last_connected_at
 * @property int $projects_count
 * @property int $agents_count
 * @property int $tools_required
 * @property int $tools_implemented
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read Collection<int, Project> $projects
 * @property-read Collection<int, Credential> $credentials
 * @property-read Collection<int, ToolContract> $ownedTools
 * @property-read Collection<int, ToolImplementation> $toolImplementations
 * @property-read Collection<int, Agent> $agents
 * @property-read Collection<int, AgentRun> $runs
 */
#[Fillable(['team_id', 'slug', 'code', 'name', 'department', 'owner_name', 'owner_email', 'environment', 'status', 'stack', 'description', 'region', 'last_connected_at', 'projects_count', 'agents_count', 'tools_required', 'tools_implemented'])]
class Application extends Model
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory, HasUuids, RecordsAuditEvents, SoftDeletes;

    /**
     * Get the team that owns the application.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the projects registered under the application.
     *
     * @return HasMany<Project, $this>
     */
    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /**
     * Get the application's environment credentials.
     *
     * @return HasMany<Credential, $this>
     */
    public function credentials(): HasMany
    {
        return $this->hasMany(Credential::class);
    }

    /**
     * Get the tool contracts owned by this application.
     *
     * @return HasMany<ToolContract, $this>
     */
    public function ownedTools(): HasMany
    {
        return $this->hasMany(ToolContract::class);
    }

    /**
     * Get the application's reported tool implementations.
     *
     * @return HasMany<ToolImplementation, $this>
     */
    public function toolImplementations(): HasMany
    {
        return $this->hasMany(ToolImplementation::class);
    }

    /**
     * Get the agents that belong to the application's projects.
     *
     * @return HasManyThrough<Agent, Project, $this>
     */
    public function agents(): HasManyThrough
    {
        return $this->hasManyThrough(Agent::class, Project::class);
    }

    /**
     * Get the agent runs recorded against the application.
     *
     * @return HasMany<AgentRun, $this>
     */
    public function runs(): HasMany
    {
        return $this->hasMany(AgentRun::class);
    }

    /**
     * Get the most relevant credential status for display.
     */
    public function credentialStatus(): string
    {
        $hasActive = $this->credentials
            ->contains(fn (Credential $credential): bool => $credential->status->isUsable());

        return $hasActive ? 'Active' : 'Revoked';
    }

    /**
     * Get a human-readable "last synced" label derived from the most recent SDK
     * credential use, or null if the application has never authenticated.
     */
    public function lastSyncedAt(): ?string
    {
        return $this->credentials
            ->max(fn (Credential $credential): ?CarbonInterface => $credential->last_used_at)
            ?->diffForHumans();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Resolve the team this application is audited under.
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
            'status' => AppStatus::class,
            'last_connected_at' => 'datetime',
            'projects_count' => 'integer',
            'agents_count' => 'integer',
            'tools_required' => 'integer',
            'tools_implemented' => 'integer',
        ];
    }
}
