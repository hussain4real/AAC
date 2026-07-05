<?php

namespace App\Policies;

use App\Models\SsoConnection;
use App\Models\User;

/**
 * Enterprise identity connections govern how web users authenticate and which
 * roles they receive, so they are managed only by Platform Admins.
 */
class SsoConnectionPolicy
{
    /**
     * Determine whether the user can view SSO connections.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->isMaaccPlatformAdmin($team);
    }

    /**
     * Determine whether the user can register an SSO connection.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->isMaaccPlatformAdmin($team);
    }

    /**
     * Determine whether the user can update the connection.
     */
    public function update(User $user, SsoConnection $ssoConnection): bool
    {
        return $user->isMaaccPlatformAdmin($ssoConnection->team);
    }

    /**
     * Determine whether the user can delete the connection.
     */
    public function delete(User $user, SsoConnection $ssoConnection): bool
    {
        return $user->isMaaccPlatformAdmin($ssoConnection->team);
    }
}
