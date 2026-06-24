<?php

namespace App\Policies;

use App\Enums\MaacPermission;
use App\Models\EvaluationDataset;
use App\Models\User;

/**
 * Golden datasets (and their cases) are managed by the same authority that
 * manages agents, since they exist to test agent behavior before promotion.
 */
class EvaluationDatasetPolicy
{
    /**
     * Determine whether the user can view any datasets.
     */
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can view the dataset.
     */
    public function view(User $user, EvaluationDataset $dataset): bool
    {
        return $user->belongsToTeam($dataset->team);
    }

    /**
     * Determine whether the user can create a dataset.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasMaacPermissionOnAnyProject($team, MaacPermission::ManageAgent);
    }

    /**
     * Determine whether the user can update the dataset (and its cases).
     */
    public function update(User $user, EvaluationDataset $dataset): bool
    {
        return $user->isMaacPlatformAdmin($dataset->team)
            || $user->hasMaacPermissionOnAnyProject($dataset->team, MaacPermission::ManageAgent);
    }

    /**
     * Determine whether the user can delete the dataset.
     */
    public function delete(User $user, EvaluationDataset $dataset): bool
    {
        return $user->isMaacPlatformAdmin($dataset->team)
            || $user->hasMaacPermissionOnAnyProject($dataset->team, MaacPermission::ManageAgent);
    }
}
