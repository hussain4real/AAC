<?php

use App\Actions\Maacc\CreateToolContract;
use App\Actions\Maacc\UpdateToolContract;
use App\Models\Application;
use Inertia\Testing\AssertableInertia;

test('the tool detail page surfaces the real version and implementation history', function () {
    [$owner, $team] = ownerAndTeam();
    $this->actingAs($owner);

    $application = Application::factory()->for($team)->create();
    $contract = app(CreateToolContract::class)->handle($team, toolContractData([
        'application_id' => $application->id,
        'name' => 'Fetch Records',
    ]));
    app(UpdateToolContract::class)->handle($contract, ['input_schema' => ['query' => 'string', 'limit' => 'number']]);

    $this->get("/{$team->slug}/tools/{$contract->slug}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('maacc/tools/show')
            ->where('id', $contract->slug)
            ->has('history.versions', 2)
            ->where('history.versions.0.version', '1.0.1')
            ->has('history.events')
        );
});

test('the tool detail page returns not found for an unknown tool', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->get("/{$team->slug}/tools/does-not-exist")
        ->assertNotFound();
});
