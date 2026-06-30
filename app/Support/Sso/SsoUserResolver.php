<?php

namespace App\Support\Sso;

use App\Enums\TeamRole;
use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\SsoConnection;
use App\Models\SsoIdentity;
use App\Models\Team;
use App\Models\User;
use App\Support\Platform\PlatformAccessManager;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Maps an external SSO identity onto a local user and the connection's team and
 * project roles. It recognizes a returning user by subject (or email), provisions
 * a new user when the connection allows it, links/refreshes the SSO identity,
 * applies the group→role mapping (the connection is authoritative for its users),
 * and records the login for the audit trail.
 */
class SsoUserResolver
{
    public function __construct(private readonly PlatformAccessManager $platformAccess = new PlatformAccessManager) {}

    /**
     * Resolve (and provision/role-map) the local user for an SSO identity.
     *
     * @throws SsoException
     */
    public function resolve(SsoConnection $connection, SsoIdentityPayload $payload): User
    {
        return DB::transaction(function () use ($connection, $payload): User {
            $identity = SsoIdentity::query()
                ->where('sso_connection_id', $connection->id)
                ->where('subject', $payload->subject)
                ->first();

            $existing = $identity !== null
                ? $identity->user
                : User::query()->where('email', $payload->email)->first();

            $provisioned = $existing === null;

            if ($provisioned && ! $connection->auto_provision) {
                throw new SsoException('no MAAC account is provisioned for this identity');
            }

            $user = $existing ?? User::create([
                'name' => $payload->name,
                'email' => $payload->email,
                'password' => Str::random(40),
            ]);

            if ($provisioned) {
                $user->forceFill(['email_verified_at' => Date::now()])->save();
            }

            $connection->identities()->updateOrCreate(
                ['subject' => $payload->subject],
                ['user_id' => $user->id, 'email' => $payload->email, 'raw_claims' => $payload->rawClaims, 'last_login_at' => Date::now()],
            );

            $teamRole = $connection->resolveTeamRole($payload->groups);
            $this->syncTeamMembership($connection->team, $user, $teamRole);

            foreach ($connection->resolveProjectRoles($payload->groups) as $assignment) {
                $this->syncProjectRole($connection->team, $user, $assignment['project'], $assignment['role']);
            }

            // Map IdP group claims onto MAAC platform-admin roles (Phase 8B). A
            // tenant user gets none unless a group is explicitly mapped, so SSO
            // never grants platform-admin access by default.
            foreach ($connection->resolvePlatformRoles($payload->groups) as $platformRole) {
                $this->platformAccess->syncSsoRole($user, $platformRole, $connection->slug);
            }

            $user->switchTeam($connection->team);
            $this->audit($connection, $user, $provisioned, $teamRole);

            return $user;
        });
    }

    /**
     * Attach or update the user's team membership role.
     */
    private function syncTeamMembership(Team $team, User $user, TeamRole $role): void
    {
        if ($team->members()->whereKey($user->id)->exists()) {
            $team->members()->updateExistingPivot($user->id, ['role' => $role->value]);

            return;
        }

        $team->members()->attach($user->id, ['role' => $role->value]);
    }

    /**
     * Attach or update the user's MAAC role on a mapped project.
     */
    private function syncProjectRole(Team $team, User $user, string $projectSlug, string $maacRole): void
    {
        $project = Project::query()
            ->whereHas('application', fn ($query) => $query->where('team_id', $team->id))
            ->where('slug', $projectSlug)
            ->first();

        if (! $project instanceof Project) {
            return;
        }

        if ($project->members()->whereKey($user->id)->exists()) {
            $project->members()->updateExistingPivot($user->id, ['maac_role' => $maacRole]);

            return;
        }

        $project->members()->attach($user->id, ['maac_role' => $maacRole]);
    }

    /**
     * Record the SSO login (or provisioning) for the audit trail.
     */
    private function audit(SsoConnection $connection, User $user, bool $provisioned, TeamRole $role): void
    {
        AuditEvent::create([
            'team_id' => $connection->team_id,
            'actor_user_id' => $user->getAuthIdentifier(),
            'actor_label' => $user->name,
            'action' => $provisioned ? 'sso.provisioned' : 'sso.login',
            'auditable_type' => $connection->getMorphClass(),
            'auditable_id' => $connection->id,
            'metadata' => ['email' => $user->email, 'team_role' => $role->value, 'connection' => $connection->slug],
            'ip_address' => request()->ip(),
        ]);
    }
}
