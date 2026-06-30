<?php

use App\Enums\PlatformRole;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use App\Support\Platform\PlatformAccessManager;
use Database\Seeders\PlatformRbacSeeder;
use Illuminate\Support\Facades\Artisan;

/**
 * Phase 8B — the scheduled access-review command expires elapsed break-glass
 * grants and reports the access that needs certification or is stale.
 */
beforeEach(function () {
    $this->seed(PlatformRbacSeeder::class);
    $this->manager = app(PlatformAccessManager::class);
    $this->actor = User::factory()->create();
});

it('expires break-glass and reports review work as a table', function () {
    $target = User::factory()->create();
    $bg = $this->manager->breakGlass($target, PlatformRole::PlatformAdmin, $this->actor, 'incident', 30);
    $bg->forceFill(['expires_at' => now()->subMinute()])->save();

    $old = $this->manager->grant(User::factory()->create(), PlatformRole::Auditor, $this->actor, 'x');
    $old->forceFill(['certified_at' => now()->subDays(200)])->save();

    $exit = Artisan::call('maac:review-platform-access');
    $output = Artisan::output();

    expect($exit)->toBe(0)
        ->and($output)->toContain('Platform access review')
        ->and($output)->toContain('1 elapsed break-glass')
        ->and($target->fresh()->hasRole(PlatformRole::PlatformAdmin->value))->toBeFalse();
});

it('emits the review as JSON', function () {
    $old = $this->manager->grant(User::factory()->create(), PlatformRole::Auditor, $this->actor, 'x');
    $old->forceFill(['certified_at' => now()->subDays(200)])->save();

    $exit = Artisan::call('maac:review-platform-access', ['--json' => true]);
    $report = json_decode(Artisan::output(), true);

    expect($exit)->toBe(0)
        ->and($report)->toHaveKeys(['expired', 'needing_certification', 'stale'])
        ->and(collect($report['needing_certification'])->pluck('role'))->toContain(PlatformRole::Auditor->value);
});

it('includes a stale grant in the review', function () {
    $staleUser = User::factory()->create();
    PlatformAccessGrant::factory()->create([
        'user_id' => $staleUser->id,
        'role' => PlatformRole::Auditor->value,
        'created_at' => now()->subDays(120),
        'certified_at' => now(),
    ]);

    Artisan::call('maac:review-platform-access', ['--json' => true]);
    $report = json_decode(Artisan::output(), true);

    expect(collect($report['stale'])->pluck('user'))->toContain($staleUser->email);
});
