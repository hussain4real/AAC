<?php

use App\Enums\PlatformRole;
use App\Models\User;
use App\Support\Platform\PlatformAccessManager;
use App\Support\Platform\PlatformAccessReport;
use Database\Seeders\PlatformRbacSeeder;

/**
 * Phase 8B — the Access Control read model: platform admins (with manager-granted,
 * SSO-granted, and directly-assigned roles), the access-review lists, the audit
 * trail, the user directory, and per-viewer capabilities.
 */
beforeEach(function () {
    $this->seed(PlatformRbacSeeder::class);
    $this->manager = app(PlatformAccessManager::class);
    $this->report = app(PlatformAccessReport::class);
    $this->actor = User::factory()->create();
});

it('reports platform admins with their grants and attribution', function () {
    // Manager-granted (granted_by set → attributed to a person).
    $byManager = User::factory()->create();
    $this->manager->grant($byManager, PlatformRole::Auditor, $this->actor, 'audit duties');

    // SSO-granted (granted_by null → attributed to "system").
    $bySso = User::factory()->create();
    $this->manager->syncSsoRole($bySso, PlatformRole::SecurityReviewer, 'okta');

    // Directly assigned with no ledger grant (the ?? collect() path).
    $direct = User::factory()->create();
    $direct->assignRole(PlatformRole::SupportOperator->value);

    $console = $this->report->forConsole();

    expect($console)->toHaveKeys(['roles', 'permissionGroups', 'admins', 'review', 'audit'])
        ->and($console['roles'])->toHaveCount(7)
        ->and($console['permissionGroups'])->not->toBeEmpty();

    $admins = collect($console['admins']);
    expect($admins->pluck('email'))->toContain($byManager->email, $bySso->email, $direct->email);

    $managerAdmin = $admins->firstWhere('email', $byManager->email);
    expect($managerAdmin['grants'][0]['grantedBy'])->toBe($this->actor->name);

    $ssoAdmin = $admins->firstWhere('email', $bySso->email);
    expect($ssoAdmin['grants'][0]['grantedBy'])->toBe('system');

    $directAdmin = $admins->firstWhere('email', $direct->email);
    expect($directAdmin['grants'])->toBe([]);

    // The grant actions wrote an audit trail.
    expect(collect($console['audit'])->pluck('action'))->toContain('platform_access.granted');
});

it('exposes the user directory and per-viewer capabilities', function () {
    $super = User::factory()->create();
    $super->assignRole(PlatformRole::SuperAdmin->value);
    $auditor = User::factory()->create();
    $auditor->assignRole(PlatformRole::Auditor->value);

    expect(collect($this->report->directory())->pluck('email'))->toContain($super->email, $auditor->email);

    $superCaps = $this->report->capabilities($super);
    expect($superCaps['isSuperAdmin'])->toBeTrue()
        ->and($superCaps['canAssignRoles'])->toBeTrue()
        ->and($superCaps['canBreakGlass'])->toBeTrue();

    $auditorCaps = $this->report->capabilities($auditor);
    expect($auditorCaps['isSuperAdmin'])->toBeFalse()
        ->and($auditorCaps['canAssignRoles'])->toBeFalse()
        ->and($auditorCaps['canReviewAccess'])->toBeFalse();
});
