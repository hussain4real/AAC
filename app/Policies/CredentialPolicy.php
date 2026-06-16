<?php

namespace App\Policies;

use App\Enums\MaacPermission;
use App\Models\Application;
use App\Models\Credential;
use App\Models\User;

/**
 * Application credentials are sensitive and managed by Platform Admins.
 */
class CredentialPolicy
{
    /**
     * Determine whether the user can view the credential metadata.
     */
    public function view(User $user, Credential $credential): bool
    {
        return $user->belongsToTeam($credential->application->team);
    }

    /**
     * Determine whether the user can generate a credential for the application.
     */
    public function create(User $user, Application $application): bool
    {
        return $user->hasMaacPermission($application->team, MaacPermission::ManageCredential);
    }

    /**
     * Determine whether the user can rotate the credential.
     */
    public function rotate(User $user, Credential $credential): bool
    {
        return $user->hasMaacPermission($credential->application->team, MaacPermission::ManageCredential);
    }

    /**
     * Determine whether the user can revoke the credential.
     */
    public function revoke(User $user, Credential $credential): bool
    {
        return $user->hasMaacPermission($credential->application->team, MaacPermission::ManageCredential);
    }
}
