<?php

namespace App\Policies;

use App\Enums\MaacPermission;
use App\Models\McpConnector;
use App\Models\User;

/**
 * MCP connectors are managed by Platform Admins and Project Owners/Developers
 * (the same authority that manages tool contracts, since a connector backs
 * server-side tools). Discovery and approval follow the tool-management and
 * tool-approval permissions respectively.
 */
class McpConnectorPolicy
{
    /**
     * Determine whether the user can view any connectors.
     */
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can view the connector.
     */
    public function view(User $user, McpConnector $connector): bool
    {
        return $user->belongsToTeam($connector->team);
    }

    /**
     * Determine whether the user can register a connector.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->hasMaacPermissionOnAnyProject($team, MaacPermission::ManageTool);
    }

    /**
     * Determine whether the user can update the connector.
     */
    public function update(User $user, McpConnector $connector): bool
    {
        return $user->isMaacPlatformAdmin($connector->team)
            || $user->hasMaacPermissionOnAnyProject($connector->team, MaacPermission::ManageTool);
    }

    /**
     * Determine whether the user can delete the connector.
     */
    public function delete(User $user, McpConnector $connector): bool
    {
        return $user->isMaacPlatformAdmin($connector->team)
            || $user->hasMaacPermissionOnAnyProject($connector->team, MaacPermission::ManageTool);
    }
}
