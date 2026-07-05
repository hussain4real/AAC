<?php

namespace App\Policies;

use App\Models\User;
use App\Models\VaultSecret;

/**
 * The secrets vault holds the platform's most sensitive material, so its
 * management is restricted to Platform Admins. Reading the plaintext never
 * happens from the console — only the runtime resolves values through the vault —
 * so there is no "reveal" capability to authorize here.
 */
class VaultSecretPolicy
{
    /**
     * Determine whether the user can view the vault inventory.
     */
    public function viewAny(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->isMaaccPlatformAdmin($team);
    }

    /**
     * Determine whether the user can store a new secret.
     */
    public function create(User $user): bool
    {
        $team = $user->currentTeam;

        return $team !== null && $user->isMaaccPlatformAdmin($team);
    }

    /**
     * Determine whether the user can rotate the secret.
     */
    public function update(User $user, VaultSecret $vaultSecret): bool
    {
        return $user->isMaaccPlatformAdmin($vaultSecret->team);
    }

    /**
     * Determine whether the user can forget the secret.
     */
    public function delete(User $user, VaultSecret $vaultSecret): bool
    {
        return $user->isMaaccPlatformAdmin($vaultSecret->team);
    }
}
