<?php

namespace App\Policies;

use App\Enums\MaacPermission;
use App\Models\ToolContract;
use App\Models\User;

/**
 * Tool contracts are managed by Platform Admins and Project Owners/Developers.
 * Approval of sensitive contracts is restricted to approvers (Project Owners
 * and Security Reviewers).
 */
class ToolContractPolicy
{
    /**
     * Determine whether the user can view any tool contracts.
     */
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can view the tool contract.
     */
    public function view(User $user, ToolContract $toolContract): bool
    {
        return $user->belongsToTeam($toolContract->team);
    }

    /**
     * Determine whether the user can create a tool contract.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasMaacPermissionOnAnyProject($team, MaacPermission::ManageTool);
    }

    /**
     * Determine whether the user can update the tool contract.
     */
    public function update(User $user, ToolContract $toolContract): bool
    {
        return $user->isMaacPlatformAdmin($toolContract->team)
            || $user->hasMaacPermissionOnAnyProject($toolContract->team, MaacPermission::ManageTool);
    }

    /**
     * Determine whether the user can approve the tool contract for production.
     */
    public function approve(User $user, ToolContract $toolContract): bool
    {
        return $user->isMaacPlatformAdmin($toolContract->team)
            || $user->hasMaacPermissionOnAnyProject($toolContract->team, MaacPermission::ApproveTool);
    }

    /**
     * Determine whether the user can delete the tool contract.
     */
    public function delete(User $user, ToolContract $toolContract): bool
    {
        return $user->isMaacPlatformAdmin($toolContract->team)
            || $user->hasMaacPermissionOnAnyProject($toolContract->team, MaacPermission::ManageTool);
    }
}
