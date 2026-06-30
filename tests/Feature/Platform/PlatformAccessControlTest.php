<?php

use App\Enums\PlatformAccessKind;
use App\Enums\PlatformRole;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\Support\Platform\PlatformAccessManager;
use Database\Seeders\PlatformRbacSeeder;
use Inertia\Testing\AssertableInertia;

/**
 * Phase 8B — the Access Control console surface: page render, permission gating,
 * the denied-tenant boundary, and the audited grant / break-glass / revoke /
 * certify actions with their Super-Admin-only restrictions.
 */
beforeEach(function () {
    $this->seed(PlatformRbacSeeder::class);
});

/**
 * A user holding the given platform role, acting under their own team.
 */
function platformUser(PlatformRole $role): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

function accessRoute(string $name, User $user, array $params = []): string
{
    return route($name, [...['current_team' => $user->currentTeam->slug], ...$params]);
}

describe('page + gating', function () {
    it('renders the Access Control page for a platform admin', function () {
        $super = platformUser(PlatformRole::SuperAdmin);

        $this->actingAs($super)
            ->get(accessRoute('access-control', $super))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('maac/access-control')
                ->has('access.roles', 7)
                ->has('access.admins')
                ->where('capabilities.isSuperAdmin', true)
                ->has('directory'));
    });

    it('forbids a tenant user with no platform role', function () {
        $tenant = User::factory()->create();

        $this->actingAs($tenant)->get(accessRoute('access-control', $tenant))->assertForbidden();
    });

    it('allows an auditor to view but not to grant', function () {
        $auditor = platformUser(PlatformRole::Auditor);

        $this->actingAs($auditor)->get(accessRoute('access-control', $auditor))->assertOk();

        // Auditor lacks roles.assign → the grant route is forbidden.
        $this->actingAs($auditor)
            ->post(accessRoute('access-control.grants.store', $auditor), [
                'user_id' => User::factory()->create()->id,
                'role' => PlatformRole::Auditor->value,
                'reason' => 'x',
            ])
            ->assertForbidden();
    });
});

describe('grant actions', function () {
    it('lets a Super Admin grant a standard platform role', function () {
        $super = platformUser(PlatformRole::SuperAdmin);
        $target = User::factory()->create();

        $this->actingAs($super)
            ->post(accessRoute('access-control.grants.store', $super), [
                'user_id' => $target->id,
                'role' => PlatformRole::SecurityReviewer->value,
                'kind' => PlatformAccessKind::Standard->value,
                'reason' => 'security review duties',
            ])
            ->assertRedirect();

        expect($target->fresh()->hasRole(PlatformRole::SecurityReviewer->value))->toBeTrue();
    });

    it('lets a Super Admin activate break-glass access', function () {
        $super = platformUser(PlatformRole::SuperAdmin);
        $target = User::factory()->create();

        $this->actingAs($super)
            ->post(accessRoute('access-control.grants.store', $super), [
                'user_id' => $target->id,
                'role' => PlatformRole::PlatformAdmin->value,
                'kind' => PlatformAccessKind::BreakGlass->value,
                'reason' => 'production incident',
                'ttl_minutes' => 30,
            ])
            ->assertRedirect();

        $grant = PlatformAccessGrant::where('user_id', $target->id)->firstOrFail();
        expect($grant->isBreakGlass())->toBeTrue()->and($grant->expires_at)->not->toBeNull();
    });

    it('forbids a non-super platform admin from assigning Super Admin or break-glass', function () {
        $admin = platformUser(PlatformRole::PlatformAdmin); // has roles.assign, not super
        $target = User::factory()->create();

        // Assigning Super Admin is Super-Admin-only.
        $this->actingAs($admin)
            ->post(accessRoute('access-control.grants.store', $admin), [
                'user_id' => $target->id,
                'role' => PlatformRole::SuperAdmin->value,
                'reason' => 'nope',
            ])
            ->assertForbidden();

        // Break-glass is Super-Admin-only.
        $this->actingAs($admin)
            ->post(accessRoute('access-control.grants.store', $admin), [
                'user_id' => $target->id,
                'role' => PlatformRole::Auditor->value,
                'kind' => PlatformAccessKind::BreakGlass->value,
                'reason' => 'nope',
            ])
            ->assertForbidden();

        // But a standard non-super grant is allowed.
        $this->actingAs($admin)
            ->post(accessRoute('access-control.grants.store', $admin), [
                'user_id' => $target->id,
                'role' => PlatformRole::Auditor->value,
                'reason' => 'ok',
            ])
            ->assertRedirect();
        expect($target->fresh()->hasRole(PlatformRole::Auditor->value))->toBeTrue();
    });
});

describe('revoke + certify', function () {
    it('revokes a grant and guards Super Admin grants', function () {
        $super = platformUser(PlatformRole::SuperAdmin);
        $admin = platformUser(PlatformRole::PlatformAdmin);
        $manager = app(PlatformAccessManager::class);

        $target = User::factory()->create();
        $standard = $manager->grant($target, PlatformRole::Auditor, $super, 'x');
        $superGrant = $manager->grant(User::factory()->create(), PlatformRole::SuperAdmin, $super, 'x');

        // A non-super platform admin cannot revoke a Super Admin grant.
        $this->actingAs($admin)
            ->post(accessRoute('access-control.grants.revoke', $admin, ['grant' => $superGrant->id]))
            ->assertForbidden();

        // A non-super platform admin can revoke a standard grant.
        $this->actingAs($admin)
            ->post(accessRoute('access-control.grants.revoke', $admin, ['grant' => $standard->id]))
            ->assertRedirect();
        expect($target->fresh()->hasRole(PlatformRole::Auditor->value))->toBeFalse();

        // The Super Admin can revoke the Super Admin grant.
        $this->actingAs($super)
            ->post(accessRoute('access-control.grants.revoke', $super, ['grant' => $superGrant->id]))
            ->assertRedirect();
        expect($superGrant->fresh()->revoked_at)->not->toBeNull();
    });

    it('certifies a grant during access review', function () {
        $super = platformUser(PlatformRole::SuperAdmin);
        $grant = app(PlatformAccessManager::class)->grant(User::factory()->create(), PlatformRole::Auditor, $super, 'x');
        $grant->forceFill(['certified_at' => now()->subDays(200)])->save();

        $this->actingAs($super)
            ->post(accessRoute('access-control.grants.certify', $super, ['grant' => $grant->id]))
            ->assertRedirect();

        expect($grant->fresh()->certified_at->isToday())->toBeTrue();
    });
});
