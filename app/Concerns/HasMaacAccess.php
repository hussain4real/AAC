<?php

namespace App\Concerns;

use App\Enums\MaacPermission;
use App\Enums\MaacRole;
use App\Enums\TeamRole;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Team;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * MAAC role-based access helpers (Phase 2 "concepts + policies" RBAC).
 *
 * Platform-level authority is derived from the team {@see TeamRole}: a team
 * Owner or Admin is treated as a MAAC Platform Admin. Finer-grained authority
 * comes from the user's {@see MaacRole} on a specific project via the
 * `project_members` pivot.
 */
trait HasMaacAccess
{
    /**
     * Get the user's MAAC project memberships.
     *
     * @return HasMany<ProjectMember, $this>
     */
    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * Determine if the user is a MAAC Platform Admin for the given team
     * (a team Owner or Admin).
     */
    public function isMaacPlatformAdmin(Team $team): bool
    {
        return $this->teamRole($team)?->isAtLeast(TeamRole::Admin) ?? false;
    }

    /**
     * Get the user's MAAC role on the given project, if any.
     */
    public function maacRoleFor(Project $project): ?MaacRole
    {
        return $this->projectMemberships()
            ->where('project_id', $project->id)
            ->first()
            ?->maac_role;
    }

    /**
     * Determine if the user has the given MAAC permission. Platform Admins hold
     * every permission; otherwise the permission must be granted by the user's
     * role on the supplied project.
     */
    public function hasMaacPermission(Team $team, MaacPermission $permission, ?Project $project = null): bool
    {
        if ($this->isMaacPlatformAdmin($team)) {
            return true;
        }

        return $project !== null
            && ($this->maacRoleFor($project)?->hasPermission($permission) ?? false);
    }

    /**
     * Determine if the user holds the given permission on any project within the
     * team (used to authorize creating project-scoped resources).
     */
    public function hasMaacPermissionOnAnyProject(Team $team, MaacPermission $permission): bool
    {
        if ($this->isMaacPlatformAdmin($team)) {
            return true;
        }

        return $this->projectMemberships()
            ->whereHas('project.application', fn ($query) => $query->where('team_id', $team->id))
            ->get()
            ->contains(fn (ProjectMember $member): bool => $member->maac_role->hasPermission($permission));
    }
}
