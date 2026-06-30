<?php

namespace App\Concerns;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use Spatie\Permission\Traits\HasRoles;

/**
 * MAAC platform-administration access helpers (Phase 8B).
 *
 * Layered on top of Spatie's {@see HasRoles}, these
 * give the global MAAC-operator roles ({@see PlatformRole}) MAAC-semantic
 * accessors. A user with no platform role is a pure tenant user; a platform role
 * grants cross-tenant administration, and {@see isPlatformSuperAdmin()} bypasses
 * every gate via the `Gate::before` override registered in the app provider.
 *
 * Permission checks go through `can()` so they compose with that override and
 * Spatie's gate-registered abilities in one place.
 */
trait HasPlatformAccess
{
    /**
     * Whether the user holds the unrestricted Super Admin platform role.
     */
    public function isPlatformSuperAdmin(): bool
    {
        return $this->hasRole(PlatformRole::SuperAdmin->value);
    }

    /**
     * Whether the user is a MAAC platform operator at all (holds any platform
     * role) — as opposed to a pure tenant user.
     */
    public function isPlatformAdministrator(): bool
    {
        return $this->hasAnyRole(PlatformRole::values());
    }

    /**
     * Whether the user holds the given platform permission (Super Admin always
     * does, via the gate override).
     */
    public function hasPlatformPermission(PlatformPermission $permission): bool
    {
        return $this->can($permission->value);
    }

    /**
     * The user's assigned platform role names (only MAAC platform roles).
     *
     * @return array<int, string>
     */
    public function platformRoleValues(): array
    {
        return $this->roles
            ->pluck('name')
            ->filter(fn (string $name): bool => in_array($name, PlatformRole::values(), true))
            ->values()
            ->all();
    }

    /**
     * The platform permission names effectively granted to the user (via roles
     * and any direct grants), intersected with the known platform catalogue.
     *
     * @return array<int, string>
     */
    public function platformPermissionValues(): array
    {
        $catalogue = PlatformPermission::values();

        return $this->getAllPermissions()
            ->pluck('name')
            ->filter(fn (string $name): bool => in_array($name, $catalogue, true))
            ->values()
            ->all();
    }
}
