<?php

use App\Models\User;

test('the public root redirects guests to the approved sign-in entry', function () {
    $this->get('/')->assertRedirect(route('login'));
});

test('the public root redirects members to their current team dashboard', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->get('/')
        ->assertRedirect(route('dashboard', ['current_team' => $team->slug]));
});

test('authenticated users without a current team reach team setup', function () {
    $user = User::factory()->create();
    $user->teams()->detach();
    $user->forceFill(['current_team_id' => null])->save();
    $user->unsetRelation('currentTeam');

    $this->actingAs($user)
        ->get('/')
        ->assertRedirect(route('teams.index'));
});

test('health readiness includes representative authenticated assets', function () {
    $this->get('/up')->assertOk();
});

test('health readiness fails when the promoted asset manifest is missing', function () {
    config(['maacc.readiness.asset_manifest' => storage_path('missing-manifest.json')]);

    $this->get('/up')->assertServerError();
});

test('health readiness fails for invalid or incomplete promoted manifests', function () {
    $manifest = storage_path('framework/testing/invalid-maacc-manifest.json');
    file_put_contents($manifest, '{invalid');
    config(['maacc.readiness.asset_manifest' => $manifest]);

    $this->get('/up')->assertServerError();

    file_put_contents($manifest, json_encode([
        'resources/js/app.tsx' => ['file' => 'assets/missing-app.js'],
        'resources/js/pages/dashboard.tsx' => ['file' => 'assets/missing-dashboard.js'],
    ]));

    $this->get('/up')->assertServerError();
    unlink($manifest);
});
