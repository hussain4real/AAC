<?php

namespace App\Policies;

use App\Enums\MaaccPermission;
use App\Models\User;

/**
 * Break-glass / incident-response controls are the platform's most powerful
 * actions, so they are restricted to Platform Admins and Security Reviewers (who
 * own incident response). There is no per-record authorization — an incident
 * action is immutable once recorded.
 */
class IncidentActionPolicy
{
    /**
     * Determine whether the user can view the incident timeline.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && (
            $user->isMaaccPlatformAdmin($team)
            || $user->hasMaaccPermissionOnAnyProject($team, MaaccPermission::ReviewSecurity)
        );
    }

    /**
     * Determine whether the user can trigger a break-glass control.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && (
            $user->isMaaccPlatformAdmin($team)
            || $user->hasMaaccPermissionOnAnyProject($team, MaaccPermission::ReviewSecurity)
        );
    }
}
