<?php

use App\Enums\PlatformAccessKind;
use App\Enums\PlatformRole;
use App\Models\AuditEvent;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\Support\Platform\PlatformAccessManager;
use Database\Seeders\PlatformRbacSeeder;

/**
 * Phase 8B — the platform access manager keeps Spatie and the audited grant
 * ledger in sync across grant, break-glass, revoke, certify, expiry, SSO sync,
 * and the access-review work lists.
 */
beforeEach(function () {
    $this->seed(PlatformRbacSeeder::class);
    $this->manager = app(PlatformAccessManager::class);
    $this->actor = User::factory()->create();
});

test('grant assigns the role and records a certified, audited grant', function () {
    $target = User::factory()->create();

    $grant = $this->manager->grant($target, PlatformRole::Auditor, $this->actor, 'needs audit access');

    expect($target->fresh()->hasRole(PlatformRole::Auditor->value))->toBeTrue()
        ->and($grant->kind)->toBe(PlatformAccessKind::Standard)
        ->and($grant->certified_at)->not->toBeNull()
        ->and($grant->granted_by)->toBe($this->actor->id);

    expect(AuditEvent::where('action', 'platform_access.granted')->where('auditable_id', $grant->id)->exists())->toBeTrue();
});

test('break-glass grants time-boxed access and clamps the TTL to the maximum', function () {
    config()->set('maac.platform.break_glass.max_ttl_minutes', 120);
    $target = User::factory()->create();

    $clamped = $this->manager->breakGlass($target, PlatformRole::PlatformAdmin, $this->actor, 'incident', 999);
    expect($clamped->isBreakGlass())->toBeTrue()
        ->and(now()->diffInMinutes($clamped->expires_at))->toBeGreaterThan(0)
        ->and(now()->diffInMinutes($clamped->expires_at))->toBeLessThanOrEqual(120)
        ->and($target->fresh()->hasRole(PlatformRole::PlatformAdmin->value))->toBeTrue();

    // A null TTL falls back to the configured default.
    config()->set('maac.platform.break_glass.default_ttl_minutes', 45);
    $defaulted = $this->manager->breakGlass(User::factory()->create(), PlatformRole::Auditor, $this->actor, 'incident');
    expect(round(now()->diffInMinutes($defaulted->expires_at)))->toBe(45.0);
});

test('revoke removes the role and audits, unless another active grant holds it', function () {
    $target = User::factory()->create();
    $first = $this->manager->grant($target, PlatformRole::Auditor, $this->actor, 'first');
    $second = $this->manager->grant($target, PlatformRole::Auditor, $this->actor, 'second');

    // Revoking one of two active grants keeps the role.
    $this->manager->revoke($first, $this->actor, 'no longer needed');
    expect($target->fresh()->hasRole(PlatformRole::Auditor->value))->toBeTrue()
        ->and($first->fresh()->revoked_at)->not->toBeNull();

    // Revoking the last active grant removes the role.
    $this->manager->revoke($second, $this->actor);
    expect($target->fresh()->hasRole(PlatformRole::Auditor->value))->toBeFalse()
        ->and($second->fresh()->revokedBy->id)->toBe($this->actor->id);

    expect(AuditEvent::where('action', 'platform_access.revoked')->count())->toBe(2);
});

test('certify resets the certification clock', function () {
    $grant = $this->manager->grant(User::factory()->create(), PlatformRole::Auditor, $this->actor, 'x');
    $grant->forceFill(['certified_at' => now()->subDays(200)])->save();

    $this->manager->certify($grant, $this->actor);

    expect($grant->fresh()->certified_at->isToday())->toBeTrue()
        ->and($grant->fresh()->certifiedBy->id)->toBe($this->actor->id)
        ->and(AuditEvent::where('action', 'platform_access.certified')->exists())->toBeTrue();
});

test('expireDueGrants revokes elapsed break-glass grants and removes the role', function () {
    $target = User::factory()->create();
    $grant = $this->manager->breakGlass($target, PlatformRole::PlatformAdmin, $this->actor, 'incident', 30);
    $grant->forceFill(['expires_at' => now()->subMinute()])->save();

    $count = $this->manager->expireDueGrants();

    expect($count)->toBe(1)
        ->and($target->fresh()->hasRole(PlatformRole::PlatformAdmin->value))->toBeFalse()
        ->and($grant->fresh()->revoked_at)->not->toBeNull()
        ->and(AuditEvent::where('action', 'platform_access.expired')->exists())->toBeTrue();
});

test('syncSsoRole grants idempotently and attributes to the system', function () {
    $target = User::factory()->create();

    $grant = $this->manager->syncSsoRole($target, PlatformRole::SecurityReviewer, 'okta-prod');
    expect($grant)->not->toBeNull()
        ->and($grant->granted_by)->toBeNull()
        ->and($target->fresh()->hasRole(PlatformRole::SecurityReviewer->value))->toBeTrue();

    // A second login does not create a duplicate grant.
    expect($this->manager->syncSsoRole($target, PlatformRole::SecurityReviewer, 'okta-prod'))->toBeNull()
        ->and(PlatformAccessGrant::where('user_id', $target->id)->count())->toBe(1);
});

test('the access-review work lists surface expiry, certification, and staleness', function () {
    // Expiring break-glass.
    $bg = $this->manager->breakGlass(User::factory()->create(), PlatformRole::PlatformAdmin, $this->actor, 'incident', 30);
    $bg->forceFill(['expires_at' => now()->subMinute()])->save();
    expect($this->manager->dueForExpiry())->toHaveCount(1);

    // Needs certification (certified long ago).
    $old = $this->manager->grant(User::factory()->create(), PlatformRole::Auditor, $this->actor, 'x');
    $old->forceFill(['certified_at' => now()->subDays(200)])->save();
    expect($this->manager->needingCertification()->pluck('id'))->toContain($old->id);

    // Stale: an old grant whose holder has had no recent activity.
    $staleUser = User::factory()->create();
    PlatformAccessGrant::factory()->create([
        'user_id' => $staleUser->id,
        'role' => PlatformRole::Auditor->value,
        'created_at' => now()->subDays(120),
        'certified_at' => now(),
    ]);
    expect($this->manager->staleGrants()->pluck('user_id'))->toContain($staleUser->id);

    // A recently-active holder of an old grant is not stale.
    $activeUser = User::factory()->create();
    PlatformAccessGrant::factory()->create([
        'user_id' => $activeUser->id,
        'role' => PlatformRole::Auditor->value,
        'created_at' => now()->subDays(120),
        'certified_at' => now(),
    ]);
    AuditEvent::factory()->create(['actor_user_id' => $activeUser->id, 'team_id' => $activeUser->currentTeam->id]);
    expect($this->manager->staleGrants()->pluck('user_id'))->not->toContain($activeUser->id);
});

test('grant isActive reflects revocation and expiry', function () {
    $active = PlatformAccessGrant::factory()->create();
    expect($active->isActive())->toBeTrue();

    expect(PlatformAccessGrant::factory()->expiredBreakGlass()->create()->isActive())->toBeFalse()
        ->and(PlatformAccessGrant::factory()->revoked()->create()->isActive())->toBeFalse();
});
