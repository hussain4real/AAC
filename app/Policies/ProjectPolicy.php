<?php

namespace App\Policies;

use App\Enums\MaacPermission;
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
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can view the project.
     */
    public function view(User $user, Project $project): bool
    {
        return $user->belongsToTeam($project->application->team);
    }

    /**
     * Determine whether the user can create a project.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasMaacPermission($team, MaacPermission::ManageProject);
    }

    /**
     * Determine whether the user can update the project.
     */
    public function update(User $user, Project $project): bool
    {
        return $user->hasMaacPermission($project->application->team, MaacPermission::ManageProject, $project);
    }

    /**
     * Determine whether the user can delete the project.
     */
    public function delete(User $user, Project $project): bool
    {
        return $user->hasMaacPermission($project->application->team, MaacPermission::ManageProject, $project);
    }
}
