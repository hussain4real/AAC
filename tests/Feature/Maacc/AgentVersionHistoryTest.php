<?php

use App\Models\AgentVersion;
use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('the agent detail page surfaces the real version history newest first', function () {
    [$owner, $team] = ownerAndTeam();
    $this->actingAs($owner);

    $agent = maaccAgent($team, ['version' => 'v2']);

    AgentVersion::factory()->for($agent)->create([
        'version' => 'v1',
        'notes' => null,
        'created_at' => now()->subDay(),
    ]);

    $publisher = User::factory()->create(['name' => 'Rana Saleh']);
    AgentVersion::factory()->for($agent)->published()->create([
        'version' => 'v2',
        'notes' => 'Tuned prompt for the delay threshold.',
        'published_by' => $publisher->id,
        'created_at' => now(),
    ]);

    $this->get("/{$team->slug}/agents/{$agent->slug}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('maacc/agents/show')
            ->where('id', $agent->slug)
            ->has('history', 2)
            ->where('history.0.version', 'v2')
            ->where('history.0.current', true)
            ->where('history.0.note', 'Tuned prompt for the delay threshold.')
            ->where('history.0.author', 'Rana Saleh')
            ->where('history.1.version', 'v1')
            ->where('history.1.current', false)
            ->where('history.1.note', null)
            ->where('history.1.author', 'system')
        );
});

test('the agent detail page returns not found for an unknown agent', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->get("/{$team->slug}/agents/does-not-exist")
        ->assertNotFound();
});

test('the agent version history is scoped to the team', function () {
    [$owner, $team] = ownerAndTeam();
    [, $otherTeam] = ownerAndTeam();

    $agent = maaccAgent($otherTeam, ['version' => 'v1']);
    AgentVersion::factory()->for($agent)->create(['version' => 'v1']);

    $this->actingAs($owner)
        ->get("/{$team->slug}/agents/{$agent->slug}")
        ->assertNotFound();
});
