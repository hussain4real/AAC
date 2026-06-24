<?php

namespace App\Policies;

use App\Enums\MaacPermission;
use App\Models\KnowledgeSource;
use App\Models\User;

/**
 * Knowledge sources back server-side knowledge-retrieval tools, so they are
 * managed by the same authority that manages tool contracts (Platform Admins and
 * project tool managers). Ingestion approval follows the tool-approval path.
 */
class KnowledgeSourcePolicy
{
    /**
     * Determine whether the user can view any knowledge sources.
     */
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can view the knowledge source.
     */
    public function view(User $user, KnowledgeSource $source): bool
    {
        return $user->belongsToTeam($source->team);
    }

    /**
     * Determine whether the user can register a knowledge source.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasMaacPermissionOnAnyProject($team, MaacPermission::ManageTool);
    }

    /**
     * Determine whether the user can update the knowledge source.
     */
    public function update(User $user, KnowledgeSource $source): bool
    {
        return $user->isMaacPlatformAdmin($source->team)
            || $user->hasMaacPermissionOnAnyProject($source->team, MaacPermission::ManageTool);
    }

    /**
     * Determine whether the user can delete the knowledge source.
     */
    public function delete(User $user, KnowledgeSource $source): bool
    {
        return $user->isMaacPlatformAdmin($source->team)
            || $user->hasMaacPermissionOnAnyProject($source->team, MaacPermission::ManageTool);
    }
}
