<?php

use App\Enums\PlatformAccessKind;
use App\Enums\PlatformPermission;
use App\Enums\PlatformRole;
use App\Models\Application;
use App\Models\User;
use Database\Seeders\PlatformRbacSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * Phase 8B — the platform RBAC foundation: the role/permission catalogue, the
 * Spatie integration on the user, and the Super Admin gate override.
 */
beforeEach(function () {
    $this->seed(PlatformRbacSeeder::class);
});

describe('platform enums', function () {
    it('exposes labels, descriptions, and permission sets for every role', function () {
        foreach (PlatformRole::cases() as $role) {
            expect($role->label())->toBeString()->not->toBe('')
                ->and($role->description())->toBeString()->not->toBe('')
                ->and($role->permissions())->not->toBeEmpty()
                ->and($role->permissionValues())->toBe(array_map(fn ($p) => $p->value, $role->permissions()));
        }

        // Super Admin holds every permission; Read-Only Observer holds only views.
        expect(PlatformRole::SuperAdmin->permissions())->toBe(PlatformPermission::cases());
        expect(collect(PlatformRole::ReadOnlyObserver->permissions())->every(
            fn (PlatformPermission $p) => $p === PlatformPermission::AccessAdmin || $p->action() === 'view',
        ))->toBeTrue();

        // Platform Admin holds everything except break-glass.
        expect(PlatformRole::PlatformAdmin->permissions())->not->toContain(PlatformPermission::ActivateBreakGlass)
            ->and(PlatformRole::values())->toHaveCount(7)
            ->and(PlatformRole::options())->toHaveCount(7);
    });

    it('exposes label, group, and action for every permission', function () {
        $manage = PlatformPermission::ManageApplications;
        expect($manage->group())->toBe('applications')
            ->and($manage->action())->toBe('manage')
            ->and($manage->label())->toBe('Applications · Manage');

        expect(PlatformPermission::values())->toHaveCount(count(PlatformPermission::cases()))
            ->and(PlatformPermission::options()[0])->toHaveKeys(['value', 'label', 'group']);
    });

    it('describes the access kind', function () {
        expect(PlatformAccessKind::BreakGlass->isBreakGlass())->toBeTrue()
            ->and(PlatformAccessKind::Standard->isBreakGlass())->toBeFalse()
            ->and(PlatformAccessKind::Standard->label())->toBe('Standard');
    });
});

describe('seeded catalogue', function () {
    it('materializes every role and permission', function () {
        expect(Role::count())->toBe(count(PlatformRole::cases()))
            ->and(Permission::count())->toBe(count(PlatformPermission::cases()));

        expect(Role::findByName(PlatformRole::Auditor->value)->permissions->pluck('name')->all())
            ->toEqualCanonicalizing(PlatformRole::Auditor->permissionValues());
    });
});

describe('HasPlatformAccess', function () {
    it('reflects a user\'s platform roles and permissions', function () {
        $auditor = User::factory()->create();
        $auditor->assignRole(PlatformRole::Auditor->value);

        expect($auditor->isPlatformAdministrator())->toBeTrue()
            ->and($auditor->isPlatformSuperAdmin())->toBeFalse()
            ->and($auditor->hasPlatformPermission(PlatformPermission::ViewAudits))->toBeTrue()
            ->and($auditor->hasPlatformPermission(PlatformPermission::ManageSettings))->toBeFalse()
            ->and($auditor->platformRoleValues())->toBe([PlatformRole::Auditor->value])
            ->and($auditor->platformPermissionValues())->toContain(PlatformPermission::ViewAudits->value);

        $tenant = User::factory()->create();
        expect($tenant->isPlatformAdministrator())->toBeFalse()
            ->and($tenant->platformRoleValues())->toBe([])
            ->and($tenant->platformPermissionValues())->toBe([]);
    });
});

describe('Super Admin gate override', function () {
    it('lets a Super Admin pass any policy gate, including cross-tenant', function () {
        // An application owned by a team the super admin does not belong to.
        $owner = User::factory()->create();
        $application = Application::factory()->for($owner->currentTeam)->create();

        $superAdmin = User::factory()->create();
        $superAdmin->assignRole(PlatformRole::SuperAdmin->value);

        // The non-member super admin is granted update via the Gate::before bypass.
        expect($superAdmin->can('update', $application))->toBeTrue();

        // A non-super platform admin gets no such bypass.
        $auditor = User::factory()->create();
        $auditor->assignRole(PlatformRole::Auditor->value);
        expect($auditor->can('update', $application))->toBeFalse();
    });
});
