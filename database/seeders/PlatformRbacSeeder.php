<?php

namespace Database\Seeders;

use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Seeds the MAAC platform-administration RBAC (Phase 8B): the granular Spatie
 * permission catalogue, the platform roles with their permission sets, and the
 * bootstrap Super Admins named in `config('maac.platform.super_admins')`.
 * Idempotent — `findOrCreate` upserts, and re-syncing permissions is safe.
 */
class PlatformRbacSeeder extends Seeder
{
    /**
     * Seed the platform roles, permissions, and bootstrap Super Admins.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (PlatformPermission::values() as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (PlatformRole::cases() as $role) {
            Role::findOrCreate($role->value, 'web')->syncPermissions($role->permissionValues());
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->assignBootstrapSuperAdmins();
    }

    /**
     * Grant the Super Admin role to every configured bootstrap admin email that
     * resolves to a user, so the platform is never left without an administrator.
     */
    private function assignBootstrapSuperAdmins(): void
    {
        /** @var array<int, string> $emails */
        $emails = config('maac.platform.super_admins', []);

        foreach ($emails as $email) {
            $user = User::firstWhere('email', $email);

            $user?->assignRole(PlatformRole::SuperAdmin->value);
        }
    }
}
