<?php

namespace App\Models;

use App\Concerns\RecordsAuditEvents;
use App\Enums\Environment;
use App\Enums\QuotaScope;
use Database\Factories\QuotaLimitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A per-day run/token quota scoped to a dimension (platform, application,
 * project, agent, or model) and optionally narrowed to a single environment.
 *
 * @property string $id
 * @property int $team_id
 * @property QuotaScope $scope
 * @property string|null $subject_id
 * @property Environment|null $environment
 * @property int|null $max_runs_per_day
 * @property int|null $max_tokens_per_day
 * @property bool $enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Team $team
 */
#[Fillable(['team_id', 'scope', 'subject_id', 'environment', 'max_runs_per_day', 'max_tokens_per_day', 'enabled'])]
class QuotaLimit extends Model
{
    /** @use HasFactory<QuotaLimitFactory> */
    use HasFactory, HasUuids, RecordsAuditEvents;

    /**
     * Get the team the quota belongs to.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Scope the query to enabled quotas.
     *
     * @param  Builder<QuotaLimit>  $query
     */
    public function scopeEnabled(Builder $query): void
    {
        $query->where('enabled', true);
    }

    /**
     * Determine whether this quota applies to a run in the given environment.
     */
    public function appliesToEnvironment(Environment $environment): bool
    {
        return $this->environment === null || $this->environment === $environment;
    }

    /**
     * Resolve the team this quota is audited under.
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
            'scope' => QuotaScope::class,
            'environment' => Environment::class,
            'max_runs_per_day' => 'integer',
            'max_tokens_per_day' => 'integer',
            'enabled' => 'boolean',
        ];
    }
}
