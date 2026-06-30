<?php

namespace App\Models;

use App\Concerns\RecordsAuditEvents;
use App\Enums\PlatformRole;
use App\Enums\SsoConnectionStatus;
use App\Enums\SsoProvider;
use App\Enums\TeamRole;
use Database\Factories\SsoConnectionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * An enterprise identity provider connection for a team. Web users authenticate
 * through it with the OAuth 2.0 / OIDC authorization-code flow; the connection's
 * claim mapping and group→role rules map the external identity onto MAAC team and
 * project roles. The client secret is encrypted at rest and never serialized.
 *
 * @property string $id
 * @property int $team_id
 * @property string $slug
 * @property string $name
 * @property SsoProvider $provider
 * @property string $authorize_url
 * @property string $token_url
 * @property string $userinfo_url
 * @property string $client_id
 * @property string|null $client_secret
 * @property string $scopes
 * @property string $email_claim
 * @property string $name_claim
 * @property string $groups_claim
 * @property TeamRole $default_team_role
 * @property array<int, array<string, mixed>>|null $group_role_mappings
 * @property bool $auto_provision
 * @property SsoConnectionStatus $status
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Team $team
 * @property-read User|null $creator
 * @property-read Collection<int, SsoIdentity> $identities
 */
#[Fillable(['team_id', 'slug', 'name', 'provider', 'authorize_url', 'token_url', 'userinfo_url', 'client_id', 'client_secret', 'scopes', 'email_claim', 'name_claim', 'groups_claim', 'default_team_role', 'group_role_mappings', 'auto_provision', 'status', 'created_by'])]
#[Hidden(['client_secret'])]
class SsoConnection extends Model
{
    /** @use HasFactory<SsoConnectionFactory> */
    use HasFactory, HasUuids, RecordsAuditEvents, SoftDeletes;

    /**
     * Get the team that owns the connection.
     *
     * @return BelongsTo<Team, $this>
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the user that registered the connection.
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the external identities linked through this connection.
     *
     * @return HasMany<SsoIdentity, $this>
     */
    public function identities(): HasMany
    {
        return $this->hasMany(SsoIdentity::class);
    }

    /**
     * Whether the connection currently accepts logins.
     */
    public function isActive(): bool
    {
        return $this->status->isActive();
    }

    /**
     * The OAuth scopes to request, as a list.
     *
     * @return array<int, string>
     */
    public function scopeList(): array
    {
        return array_values(array_filter(explode(' ', trim($this->scopes))));
    }

    /**
     * Resolve the highest mapped team role for a set of external groups, falling
     * back to the connection's default role.
     *
     * @param  array<int, string>  $groups
     */
    public function resolveTeamRole(array $groups): TeamRole
    {
        $matched = collect($this->group_role_mappings ?? [])
            ->filter(fn (array $mapping): bool => in_array($mapping['group'] ?? null, $groups, true))
            ->map(fn (array $mapping): ?TeamRole => TeamRole::tryFrom((string) ($mapping['team_role'] ?? '')))
            ->filter()
            ->sortByDesc(fn (TeamRole $role): int => $role->level());

        return $matched->first() ?? $this->default_team_role;
    }

    /**
     * Resolve the project MaacRole assignments mapped from a set of external
     * groups: a list of [project_slug, maac_role] pairs.
     *
     * @param  array<int, string>  $groups
     * @return array<int, array{project: string, role: string}>
     */
    public function resolveProjectRoles(array $groups): array
    {
        return collect($this->group_role_mappings ?? [])
            ->filter(fn (array $mapping): bool => in_array($mapping['group'] ?? null, $groups, true)
                && ! empty($mapping['project_slug']) && ! empty($mapping['maac_role']))
            ->map(fn (array $mapping): array => ['project' => (string) $mapping['project_slug'], 'role' => (string) $mapping['maac_role']])
            ->values()
            ->all();
    }

    /**
     * Resolve the MAAC platform roles ({@see PlatformRole}) mapped from a set of
     * external groups. A tenant user gets no platform role unless an IdP group is
     * explicitly mapped to one, so platform-admin access is never granted by
     * default (Phase 8B).
     *
     * @param  array<int, string>  $groups
     * @return array<int, PlatformRole>
     */
    public function resolvePlatformRoles(array $groups): array
    {
        return collect($this->group_role_mappings ?? [])
            ->filter(fn (array $mapping): bool => in_array($mapping['group'] ?? null, $groups, true))
            ->map(fn (array $mapping): ?PlatformRole => PlatformRole::tryFrom((string) ($mapping['platform_role'] ?? '')))
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get the route key for the model.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /**
     * Generate a unique connection slug from a name.
     */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'sso';
        $slug = $base;
        $suffix = 1;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * Resolve the team this connection is audited under.
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
            'provider' => SsoProvider::class,
            'status' => SsoConnectionStatus::class,
            'default_team_role' => TeamRole::class,
            'group_role_mappings' => 'array',
            'auto_provision' => 'boolean',
            'client_secret' => 'encrypted',
        ];
    }
}
