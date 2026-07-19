<?php

namespace App\Policies;

use App\Enums\MaaccPermission;
use App\Models\Agent;
use App\Models\Project;
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
        $team = $user->currentTeam;

        return $team !== null && $user->hasMaaccPermissionOnAnyProject($team, MaaccPermission::View);
    }

    /**
     * Determine whether the user can view the tool contract.
     */
    public function view(User $user, ToolContract $toolContract): bool
    {
        if ($user->isMaaccPlatformAdmin($toolContract->team)) {
            return true;
        }

        if ($toolContract->application_id !== null) {
            return $toolContract->application->projects()->get()->contains(
                fn (Project $project): bool => $user->hasMaaccPermission($toolContract->team, MaaccPermission::View, $project),
            );
        }

        return $toolContract->agents()->with('project')->get()->contains(
            fn (Agent $agent): bool => $user->hasMaaccPermission($toolContract->team, MaaccPermission::View, $agent->project),
        );
    }

    /**
     * Determine whether the user can create a tool contract.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasMaaccPermissionOnAnyProject($team, MaaccPermission::ManageTool);
    }

    /**
     * Determine whether the user can update the tool contract.
     */
    public function update(User $user, ToolContract $toolContract): bool
    {
        return $user->isMaaccPlatformAdmin($toolContract->team)
            || $user->hasMaaccPermissionOnAnyProject($toolContract->team, MaaccPermission::ManageTool);
    }

    /**
     * Determine whether the user can approve the tool contract for production.
     */
    public function approve(User $user, ToolContract $toolContract): bool
    {
        return $user->isMaaccPlatformAdmin($toolContract->team)
            || $user->hasMaaccPermissionOnAnyProject($toolContract->team, MaaccPermission::ApproveTool);
    }

    /**
     * Determine whether the user can delete the tool contract.
     */
    public function delete(User $user, ToolContract $toolContract): bool
    {
        return $user->isMaaccPlatformAdmin($toolContract->team)
            || $user->hasMaaccPermissionOnAnyProject($toolContract->team, MaaccPermission::ManageTool);
    }
}
