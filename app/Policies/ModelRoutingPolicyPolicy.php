<?php

namespace App\Policies;

use App\Enums\MaaccPermission;
use App\Models\ModelRoutingPolicy;
use App\Models\User;

/**
 * Advanced model routing policies are managed by Platform Admins and anyone who
 * can manage agents (a routing policy governs how an agent selects its model).
 */
class ModelRoutingPolicyPolicy
{
    /**
     * Determine whether the user can view routing policies.
     */
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can create a routing policy.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && (
            $user->isMaaccPlatformAdmin($team)
            || $user->hasMaaccPermissionOnAnyProject($team, MaaccPermission::ManageAgent)
        );
    }

    /**
     * Determine whether the user can update the routing policy.
     */
    public function update(User $user, ModelRoutingPolicy $policy): bool
    {
        return $user->isMaaccPlatformAdmin($policy->team)
            || $user->hasMaaccPermissionOnAnyProject($policy->team, MaaccPermission::ManageAgent);
    }

    /**
     * Determine whether the user can delete the routing policy.
     */
    public function delete(User $user, ModelRoutingPolicy $policy): bool
    {
        return $user->isMaaccPlatformAdmin($policy->team)
            || $user->hasMaaccPermissionOnAnyProject($policy->team, MaaccPermission::ManageAgent);
    }
}
