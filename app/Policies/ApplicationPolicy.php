<?php

namespace App\Policies;

use App\Enums\MaacPermission;
use App\Models\Application;
use App\Models\User;

/**
 * Applications are team-level records managed by MAAC Platform Admins.
 */
class ApplicationPolicy
{
    /**
     * Determine whether the user can view any applications.
     */
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can view the application.
     */
    public function view(User $user, Application $application): bool
    {
        return $user->belongsToTeam($application->team);
    }

    /**
     * Determine whether the user can register an application.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasMaacPermission($team, MaacPermission::ManageApplication);
    }

    /**
     * Determine whether the user can update the application.
     */
    public function update(User $user, Application $application): bool
    {
        return $user->hasMaacPermission($application->team, MaacPermission::ManageApplication);
    }

    /**
     * Determine whether the user can delete the application.
     */
    public function delete(User $user, Application $application): bool
    {
        return $user->hasMaacPermission($application->team, MaacPermission::ManageApplication);
    }
}
