<?php

use App\Actions\Maacc\CreateToolContract;
use App\Models\Application;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('the version journey page renders with the team report', function () {
    [$owner, $team] = ownerAndTeam();
    $this->actingAs($owner);

    $application = Application::factory()->for($team)->create();
    $contract = app(CreateToolContract::class)->handle($team, toolContractData([
        'application_id' => $application->id,
        'name' => 'Fetch Records',
    ]));

    $this->get("/{$team->slug}/journey")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('maacc/journey')
            ->has('journey.tools', 1)
            ->where('journey.tools.0.slug', $contract->slug)
            ->where('journey.tools.0.current_version', '1.0.0')
            ->has('journey.applications', 1)
            ->where('journey.truncated', false)
        );
});

test('the version journey page renders an empty report for a team with no client tools', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->get("/{$team->slug}/journey")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('maacc/journey')
            ->has('journey.tools', 0)
            ->has('journey.applications', 0)
        );
});

test('a non-member cannot view another team version journey', function () {
    [, $team] = ownerAndTeam();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)
        ->get("/{$team->slug}/journey")
        ->assertForbidden();
});
