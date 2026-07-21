<?php

use App\Enums\PlatformRole;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\PlatformRbacSeeder;

beforeEach(function () {
    Carbon::setTestNow('2026-07-19 09:00:00');
    $this->seed(PlatformRbacSeeder::class);
    $this->operator = User::factory()->create([
        'name' => 'MAACC Enterprise Operator',
        'email' => 'enterprise.operator@example.test',
    ]);
    $this->operator->currentTeam->update(['name' => 'Enterprise Readiness Team']);
    $this->operator->assignRole(PlatformRole::SuperAdmin->value);
    $this->slug = $this->operator->currentTeam->slug;
    $this->actingAs($this->operator);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('the core operator journey has no critical axe, console, script, or image failures', function () {
    $routes = [
        'dashboard',
        'applications',
        'projects',
        'agents',
        'tools',
        'runs',
        'governance',
        'platform-settings',
        'access-control',
    ];

    foreach ($routes as $routeName) {
        visit(route($routeName, ['current_team' => $this->slug]))
            ->wait(0.5)
            ->assertNoSmoke()
            ->assertNoBrokenImages()
            ->assertNoAccessibilityIssues(1);
    }
});

test('the dashboard remains usable without horizontal overflow at enterprise breakpoints', function () {
    foreach ([320, 375, 768, 1024, 1440] as $width) {
        visit(route('dashboard', ['current_team' => $this->slug]))
            ->resize($width, 900)
            ->assertScript('document.documentElement.scrollWidth <= document.documentElement.clientWidth')
            ->assertNoSmoke();
    }
});

test('the desktop dashboard matches its approved visual baseline', function () {
    visit(route('dashboard', ['current_team' => $this->slug]))
        ->resize(1440, 1000)
        ->assertScreenshotMatches();
});

test('the mobile dashboard matches its approved visual baseline', function () {
    visit(route('dashboard', ['current_team' => $this->slug]))
        ->resize(375, 812)
        ->assertScreenshotMatches();
});

test('the compact mobile dashboard matches its approved visual baseline', function () {
    visit(route('dashboard', ['current_team' => $this->slug]))
        ->resize(320, 812)
        ->assertScreenshotMatches();
});

test('the tablet dashboard matches its approved visual baseline', function () {
    visit(route('dashboard', ['current_team' => $this->slug]))
        ->resize(768, 900)
        ->assertScreenshotMatches();
});

test('the compact desktop dashboard matches its approved visual baseline', function () {
    visit(route('dashboard', ['current_team' => $this->slug]))
        ->resize(1024, 900)
        ->assertScreenshotMatches();
});
