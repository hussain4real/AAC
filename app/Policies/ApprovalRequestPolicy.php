<?php

namespace App\Policies;

use App\Enums\MaacPermission;
use App\Models\ApprovalRequest;
use App\Models\User;

/**
 * Governance approvals: any developer/owner may request one; deciding is
 * restricted to approvers (Platform Admins, Project Owners, Security Reviewers).
 */
class ApprovalRequestPolicy
{
    /**
     * Determine whether the user can view the approval queue.
     */
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can request an approval.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        if ($team === null) {
            return false;
        }

        return $user->isMaacPlatformAdmin($team)
            || $user->hasMaacPermissionOnAnyProject($team, MaacPermission::ManageAgent)
            || $user->hasMaacPermissionOnAnyProject($team, MaacPermission::ManageTool);
    }

    /**
     * Determine whether the user can approve or reject the request.
     */
    public function decide(User $user, ApprovalRequest $approvalRequest): bool
    {
        return $user->isMaacPlatformAdmin($approvalRequest->team)
            || $user->hasMaacPermissionOnAnyProject($approvalRequest->team, MaacPermission::ApproveTool);
    }
}
