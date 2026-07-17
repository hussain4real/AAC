<?php

namespace App\Concerns;

use App\Enums\MaaccPermission;
use App\Enums\MaaccRole;
use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MAACC role-based access helpers (Phase 2 "concepts + policies" RBAC).
 *
 * Platform-level authority is derived from the team {@see TeamRole}: a team
 * Owner or Admin is treated as a MAACC Platform Admin. Finer-grained authority
 * comes from the user's {@see MaaccRole} on a specific project via the
 * `project_members` pivot.
 */
trait HasMaaccAccess
{
    /**
     * Get the user's MAACC project memberships.
     *
     * @return HasMany<ProjectMember, $this>
     */
    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * Determine if the user is a MAACC Platform Admin for the given team
     * (a team Owner or Admin).
     */
    public function isMaaccPlatformAdmin(Team $team): bool
    {
        return $this->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    /**
     * Get the user's MAACC role on the given project, if any.
     */
    public function maaccRoleFor(Project $project): ?MaaccRole
    {
        return $this->projectMemberships()
            ->where('project_id', $project->id)
            ->first()
            ?->maacc_role;
    }

    /**
     * Determine if the user has the given MAACC permission. Platform Admins hold
     * every permission; otherwise the permission must be granted by the user's
     * role on the supplied project.
     */
    public function hasMaaccPermission(Team $team, MaaccPermission $permission, ?Project $project = null): bool
    {
        if ($this->isMaaccPlatformAdmin($team)) {
            return true;
        }

        return $project !== null
            && ($this->maaccRoleFor($project)?->hasPermission($permission) ?? false);
    }

    /**
     * Determine if the user holds the given permission on any project within the
     * team (used to authorize creating project-scoped resources).
     */
    public function hasMaaccPermissionOnAnyProject(Team $team, MaaccPermission $permission): bool
    {
        if ($this->isMaaccPlatformAdmin($team)) {
            return true;
        }

        return $this->projectMemberships()
            ->whereHas('project.application', fn ($query) => $query->where('team_id', $team->id))
            ->get()
            ->contains(fn (ProjectMember $member): bool => $member->maacc_role?->hasPermission($permission) ?? false);
    }
}
