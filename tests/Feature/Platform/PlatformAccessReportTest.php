<?php

use App\Enums\PlatformRole;
use App\Models\User;
use App\Support\Platform\PlatformAccessManager;
use App\Support\Platform\PlatformAccessReport;
use Database\Seeders\PlatformRbacSeeder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

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

it('bounds and searches the user directory with cursor metadata', function () {
    User::factory()->count(60)->sequence(
        fn ($sequence): array => [
            'name' => sprintf('Directory User %03d', $sequence->index),
            'email' => sprintf('directory%03d@example.test', $sequence->index),
        ],
    )->create();

    $firstPage = $this->report->directoryPage(Request::create('/access-control', 'GET', ['per_page' => 25]));
    $searchPage = $this->report->directoryPage(Request::create('/access-control', 'GET', ['directory_q' => 'directory042']));

    expect($firstPage['items'])->toHaveCount(25)
        ->and($firstPage['pagination']['hasMore'])->toBeTrue()
        ->and($firstPage['pagination']['nextCursor'])->not->toBeNull()
        ->and($searchPage['items'])->toHaveCount(1)
        ->and($searchPage['items'][0]['email'])->toBe('directory042@example.test');
});

it('keeps the access report bounded with ten thousand platform administrators', function () {
    $role = Role::findByName(PlatformRole::Auditor->value);
    $firstId = ((int) User::query()->max('id')) + 1;
    $now = now()->format('Y-m-d H:i:s');

    foreach (array_chunk(range(0, 9_999), 500) as $offsets) {
        DB::table('users')->insert(array_map(fn (int $offset): array => [
            'id' => $firstId + $offset,
            'name' => sprintf('Scale Admin %05d', $offset),
            'email' => sprintf('scale-admin-%05d@example.test', $offset),
            'password' => 'not-used',
            'created_at' => $now,
            'updated_at' => $now,
        ], $offsets));

        DB::table('model_has_roles')->insert(array_map(fn (int $offset): array => [
            'role_id' => $role->id,
            'model_type' => User::class,
            'model_id' => $firstId + $offset,
        ], $offsets));

        DB::table('platform_access_grants')->insert(array_map(fn (int $offset): array => [
            'id' => (string) Str::uuid(),
            'user_id' => $firstId + $offset,
            'role' => PlatformRole::Auditor->value,
            'kind' => 'standard',
            'reason' => 'enterprise scale test',
            'certified_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ], $offsets));
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    $console = $this->report->forConsole(Request::create('/access-control', 'GET', ['per_page' => 25]));
    $queryCount = count(DB::getQueryLog());
    DB::disableQueryLog();

    expect($console['admins'])->toHaveCount(25)
        ->and($console['pagination']['admins']['hasMore'])->toBeTrue()
        ->and($console['pagination']['admins']['nextCursor'])->not->toBeNull()
        ->and($queryCount)->toBeLessThanOrEqual(15)
        ->and(strlen((string) json_encode($console)))->toBeLessThan(400_000);
});
