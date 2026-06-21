<?php

namespace App\Policies;

use App\Enums\MaacPermission;
use App\Models\User;

/**
 * Audit events are viewable by Platform Admins, Auditors, and Security
 * Reviewers (holders of the audit:view permission).
 */
class AuditEventPolicy
{
    /**
     * Determine whether the user can view the team's audit log.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null
            && ($user->isMaacPlatformAdmin($team) || $user->hasMaacPermissionOnAnyProject($team, MaacPermission::ViewAudit));
    }
}
