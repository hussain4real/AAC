<?php

namespace App\Policies;

use App\Enums\MaaccPermission;
use App\Models\Application;
use App\Models\Project;
use App\Models\User;

/**
 * Projects live under applications and are managed by Platform Admins and the
 * project's own Project Owners.
 */
class ProjectPolicy
{
    /**
     * Determine whether the user can view any projects.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasMaaccPermissionOnAnyProject($team, MaaccPermission::View);
    }

    /**
     * Determine whether the user can view the project.
     */
    public function view(User $user, Project $project): bool
    {
        return $user->hasMaaccPermission($project->application->team, MaaccPermission::View, $project);
    }

    /**
     * Determine whether the user can create a project.
     */
    public function create(User $user, ?Application $application = null): bool
    {
        $team = $user->currentTeam;

        return $team !== null
            && ($application === null || $application->team_id === $team->id)
            && $user->hasMaaccPermission($team, MaaccPermission::ManageProject);
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->hasMaaccPermission($project->application->team, MaaccPermission::ManageProject, $project);
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->hasMaaccPermission($project->application->team, MaaccPermission::ManageProject, $project);
    }

    /**
     * Determine whether the user can administer project membership.
     */
    public function manageMembers(User $user, Project $project): bool
    {
        return $user->hasMaaccPermission($project->application->team, MaaccPermission::ManageProject, $project);
    }
}
