<?php

namespace App\Policies;

use App\Models\GovernanceSetting;
use App\Models\User;

/**
 * Governance settings (retention, masking, quotas) are viewable by team members
 * and editable only by Platform Admins.
 */
class GovernanceSettingPolicy
{
    /**
     * Determine whether the user can view the governance settings.
     */
    public function view(User $user, GovernanceSetting $governanceSetting): bool
    {
        return $user->belongsToTeam($governanceSetting->team);
    }

    /**
     * Determine whether the user can update the governance settings.
     */
    public function update(User $user, GovernanceSetting $governanceSetting): bool
    {
        return $user->isMaacPlatformAdmin($governanceSetting->team);
    }
}
