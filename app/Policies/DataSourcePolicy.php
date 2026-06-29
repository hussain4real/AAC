<?php

namespace App\Policies;

use App\Enums\MaacPermission;
use App\Models\DataSource;
use App\Models\User;

/**
 * Read-only data sources back server-side `db` tools, so they are managed by the
 * same authority that manages tool contracts (Platform Admins and project tool
 * managers). Access approval for a sensitive source follows the tool-approval
 * path.
 */
class DataSourcePolicy
{
    /**
     * Determine whether the user can view any data sources.
     */
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can view the data source.
     */
    public function view(User $user, DataSource $source): bool
    {
        return $user->belongsToTeam($source->team);
    }

    /**
     * Determine whether the user can register a data source.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasMaacPermissionOnAnyProject($team, MaacPermission::ManageTool);
    }

    /**
     * Determine whether the user can update the data source.
     */
    public function update(User $user, DataSource $source): bool
    {
        return $user->isMaacPlatformAdmin($source->team)
            || $user->hasMaacPermissionOnAnyProject($source->team, MaacPermission::ManageTool);
    }

    /**
     * Determine whether the user can delete the data source.
     */
    public function delete(User $user, DataSource $source): bool
    {
        return $user->isMaacPlatformAdmin($source->team)
            || $user->hasMaacPermissionOnAnyProject($source->team, MaacPermission::ManageTool);
    }
}
