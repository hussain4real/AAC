<?php

namespace App\Policies;

use App\Enums\MaacPermission;
use App\Models\LlmProvider;
use App\Models\User;

/**
 * The approved LLM catalog is governed by Platform Admins.
 */
class LlmProviderPolicy
{
    /**
     * Determine whether the user can view the model catalog.
     */
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, LlmProvider $llmProvider): bool
    {
        return $user->belongsToTeam($llmProvider->team);
    }

    /**
     * Determine whether the user can add a model to the catalog.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasMaacPermission($team, MaacPermission::ManagePlatform);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, LlmProvider $llmProvider): bool
    {
        return $user->hasMaacPermission($llmProvider->team, MaacPermission::ManagePlatform);
    }

    /**
     * Determine whether the user can remove the model from the catalog.
     */
    public function delete(User $user, LlmProvider $llmProvider): bool
    {
        return $user->hasMaacPermission($llmProvider->team, MaacPermission::ManagePlatform);
    }
}
