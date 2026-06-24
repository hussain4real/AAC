<?php

namespace App\Policies;

use App\Enums\MaacPermission;
use App\Models\Evaluation;
use App\Models\User;

/**
 * Evaluations are run and managed by the same authority that manages agents,
 * since they assess an agent's behavior and gate its promotion.
 */
class EvaluationPolicy
{
    /**
     * Determine whether the user can view any evaluations.
     */
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can view the evaluation.
     */
    public function view(User $user, Evaluation $evaluation): bool
    {
        return $user->belongsToTeam($evaluation->team);
    }

    /**
     * Determine whether the user can run an evaluation.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasMaacPermissionOnAnyProject($team, MaacPermission::ManageAgent);
    }

    /**
     * Determine whether the user can delete the evaluation.
     */
    public function delete(User $user, Evaluation $evaluation): bool
    {
        return $user->isMaacPlatformAdmin($evaluation->team)
            || $user->hasMaacPermissionOnAnyProject($evaluation->team, MaacPermission::ManageAgent);
    }
}
