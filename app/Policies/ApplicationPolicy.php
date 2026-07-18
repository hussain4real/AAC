<?php

namespace App\Policies;

use App\Enums\MaaccPermission;
use App\Models\Application;
use App\Models\Project;
use App\Models\User;

/**
 * Applications are team-level records managed by MAACC Platform Admins.
 */
class ApplicationPolicy
{
    /**
     * Determine whether the user can view any applications.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasMaaccPermissionOnAnyProject($team, MaaccPermission::View);
    }

    /**
     * Determine whether the user can view the application.
     */
    public function view(User $user, Application $application): bool
    {
        if ($user->isMaaccPlatformAdmin($application->team)) {
            return true;
        }

        return $application->projects()->get()->contains(
            fn (Project $project): bool => $user->hasMaaccPermission($application->team, MaaccPermission::View, $project),
        );
    }

    /**
     * Determine whether the user can register an application.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasMaaccPermission($team, MaaccPermission::ManageApplication);
    }

    /**
     * Determine whether the user can update the application.
     */
    public function update(User $user, Application $application): bool
    {
        return $user->hasMaaccPermission($application->team, MaaccPermission::ManageApplication);
    }

    /**
     * Determine whether the user can delete the application.
     */
    public function delete(User $user, Application $application): bool
    {
        return $user->hasMaaccPermission($application->team, MaaccPermission::ManageApplication);
    }
}
