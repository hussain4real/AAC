<?php

use App\Models\Application;
use Inertia\Testing\AssertableInertia as Assert;

test('a platform admin can register an application', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('applications.store', ['current_team' => $team->slug]), [
            'name' => 'Marine Ops',
            'code' => 'marine-ops',
            'department' => 'Maritime',
            'owner_name' => 'Khalid',
            'owner_email' => 'k@milaha.com',
            'environment' => 'production',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('applications', [
        'team_id' => $team->id,
        'code' => 'marine-ops',
        'name' => 'Marine Ops',
    ]);
});

test('application registration validates required fields', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('applications.store', ['current_team' => $team->slug]), [])
        ->assertSessionHasErrors(['name', 'code', 'department', 'owner_name', 'owner_email', 'environment']);
});

test('application registration rejects an invalid environment', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('applications.store', ['current_team' => $team->slug]), [
            'name' => 'X', 'code' => 'x', 'department' => 'D',
            'owner_name' => 'O', 'owner_email' => 'o@e.com', 'environment' => 'orbit',
        ])
        ->assertSessionHasErrors('environment');
});

test('a non-admin team member cannot register an application', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);

    $this->actingAs($member)
        ->post(route('applications.store', ['current_team' => $team->slug]), [
            'name' => 'X', 'code' => 'x', 'department' => 'D',
            'owner_name' => 'O', 'owner_email' => 'o@e.com', 'environment' => 'production',
        ])
        ->assertForbidden();
});

test('a platform admin can update an application', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();

    $this->actingAs($owner)
        ->put(route('applications.update', ['current_team' => $team->slug, 'application' => $application->slug]), [
            'name' => 'Renamed Portal',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('applications', ['id' => $application->id, 'name' => 'Renamed Portal']);
});

test('a platform admin can archive an application', function () {
    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();

    $this->actingAs($owner)
        ->delete(route('applications.destroy', ['current_team' => $team->slug, 'application' => $application->slug]))
        ->assertRedirect();

    $this->assertSoftDeleted('applications', ['id' => $application->id]);
});

test('the console only shares the current team applications', function () {
    [$owner, $team] = ownerAndTeam();
    Application::factory()->for($team)->count(2)->create();
    Application::factory()->count(3)->create(); // other teams

    $this->actingAs($owner)
        ->get(route('applications', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('maac.apps', 2));
});

test('administrative changes record an audit event', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)->post(route('applications.store', ['current_team' => $team->slug]), [
        'name' => 'Audited App', 'code' => 'audited', 'department' => 'D',
        'owner_name' => 'O', 'owner_email' => 'o@e.com', 'environment' => 'production',
    ]);

    $this->assertDatabaseHas('audit_events', [
        'team_id' => $team->id,
        'action' => 'application.registered',
    ]);
});
