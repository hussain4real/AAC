<?php

namespace App\Policies;

use App\Models\QuotaLimit;
use App\Models\User;

/**
 * Rate limits / quotas are managed by Platform Admins.
 */
class QuotaLimitPolicy
{
    /**
     * Determine whether the user can manage quotas for the current team.
     */
    public function create(User $user): bool
    {
        return $user->currentTeam !== null && $user->isMaacPlatformAdmin($user->currentTeam);
    }

    /**
     * Determine whether the user can update the quota.
     */
    public function update(User $user, QuotaLimit $quotaLimit): bool
    {
        return $user->isMaacPlatformAdmin($quotaLimit->team);
    }

    /**
     * Determine whether the user can delete the quota.
     */
    public function delete(User $user, QuotaLimit $quotaLimit): bool
    {
        return $user->isMaacPlatformAdmin($quotaLimit->team);
    }
}
