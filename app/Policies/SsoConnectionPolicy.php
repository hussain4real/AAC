<?php

namespace App\Policies;

use App\Enums\PlatformPermission;
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
        return $user->hasPlatformPermission(PlatformPermission::ViewIdentity);
    }

    /**
     * Determine whether the user can register an SSO connection.
     */
    public function create(User $user): bool
    {
        return $user->hasPlatformPermission(PlatformPermission::ManageIdentity);
    }

    /**
     * Determine whether the user can update the connection.
     */
    public function update(User $user, SsoConnection $ssoConnection): bool
    {
        return $user->hasPlatformPermission(PlatformPermission::ManageIdentity);
    }

    /**
     * Determine whether the user can delete the connection.
     */
    public function delete(User $user, SsoConnection $ssoConnection): bool
    {
        return $user->hasPlatformPermission(PlatformPermission::ManageIdentity);
    }

    /**
     * Determine whether the user may test a connection before approval.
     */
    public function test(User $user, SsoConnection $ssoConnection): bool
    {
        return $user->hasPlatformPermission(PlatformPermission::ManageIdentity);
    }

    /**
     * Determine whether the user may independently approve activation.
     */
    public function approve(User $user, SsoConnection $ssoConnection): bool
    {
        return $user->hasPlatformPermission(PlatformPermission::ApproveIdentity);
    }
}
