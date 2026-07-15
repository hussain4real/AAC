<?php

namespace App\Support\Sso;

use App\Enums\SsoFailureCode;
use App\Enums\TeamRole;
use App\Models\AuditEvent;
use App\Models\Project;
use App\Models\SsoConnection;
use App\Models\SsoIdentity;
use App\Models\Team;
use App\Models\User;
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
                ->lockForUpdate()
                ->first();

            $existing = $identity?->user;

            if ($existing !== null && $existing->isPlatformAdministrator()) {
                throw new SsoException('tenant SSO cannot authenticate a platform administrator', SsoFailureCode::PlatformAdministratorIdentity);
            }

            if ($existing !== null && strcasecmp($existing->email, $payload->email) !== 0) {
                throw new SsoException('the identity email no longer matches its provisioned account', SsoFailureCode::IdentityEmailMismatch);
            }

            $provisioned = $existing === null;

            if ($provisioned && ! $connection->auto_provision) {
                throw new SsoException('no MAACC account is provisioned for this identity', SsoFailureCode::IdentityNotProvisioned);
            }

            if ($provisioned && User::query()->where('email', $payload->email)->lockForUpdate()->exists()) {
                throw new SsoException('an account with this email already exists and cannot be linked automatically', SsoFailureCode::EmailCollision);
            }

            $user = $existing ?? User::create([
                'name' => $payload->name,
                'email' => $payload->email,
                'password' => Str::random(40),
            ]);

            if ($provisioned) {
                $user->forceFill(['email_verified_at' => Date::now()])->save();
            }

            $identity ??= new SsoIdentity([
                'sso_connection_id' => $connection->id,
                'subject' => $payload->subject,
                'user_id' => $user->id,
            ]);

            $identity->fill([
                'email' => $payload->email,
                'raw_claims' => $payload->rawClaims,
                'last_login_at' => Date::now(),
            ]);

            $teamRole = $connection->resolveTeamRole($payload->groups);
            $this->syncTeamMembership($connection->team, $user, $teamRole);

            $managedProjectIds = $this->reconcileProjectRoles($connection->team, $user, $identity, $connection->resolveProjectRoles($payload->groups));
            $identity->managed_project_ids = $managedProjectIds;
            $identity->save();

            $user->switchTeam($connection->team);
            $this->audit($connection, $user, $provisioned, $teamRole);

            return $user;
        });
    }

    /**
     * Make the connection authoritative for the project memberships it created:
     * current mappings are applied and previously managed mappings that vanished
     * from the IdP claims are removed.
     *
     * @param  array<int, array{project: string, role: string}>  $assignments
     * @return array<int, string>
     */
    private function reconcileProjectRoles(Team $team, User $user, SsoIdentity $identity, array $assignments): array
    {
        $managedProjectIds = [];

        foreach ($assignments as $assignment) {
            $project = $this->syncProjectRole($team, $user, $assignment['project'], $assignment['role']);

            if ($project !== null) {
                $managedProjectIds[] = $project->id;
            }
        }

        $removedProjectIds = array_diff($identity->managed_project_ids ?? [], $managedProjectIds);

        if ($removedProjectIds !== []) {
            $user->projectMemberships()->whereIn('project_id', $removedProjectIds)->delete();
        }

        return array_values(array_unique($managedProjectIds));
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
     * Attach or update the user's MAACC role on a mapped project.
     */
    private function syncProjectRole(Team $team, User $user, string $projectSlug, string $maaccRole): ?Project
    {
        $project = Project::query()
            ->whereHas('application', fn ($query) => $query->where('team_id', $team->id))
            ->where('slug', $projectSlug)
            ->first();

        if (! $project instanceof Project) {
            return null;
        }

        if ($project->members()->whereKey($user->id)->exists()) {
            $project->members()->updateExistingPivot($user->id, ['maacc_role' => $maaccRole]);

            return $project;
        }

        $project->members()->attach($user->id, ['maacc_role' => $maaccRole]);

        return $project;
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
