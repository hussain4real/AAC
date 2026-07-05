<?php

use App\Enums\TraceEventType;
use App\Models\TraceEvent;
use Inertia\Testing\AssertableInertia;

test('the run detail page surfaces the real ordered execution trace', function () {
    [$owner, $team] = ownerAndTeam();
    $this->actingAs($owner);

    $run = maaccRun(maaccAgent($team));

    // Recorded out of order to prove the page orders by sequence.
    TraceEvent::factory()->create([
        'agent_run_id' => $run->id,
        'type' => TraceEventType::Completed,
        'sequence' => 2,
    ]);
    TraceEvent::factory()->create([
        'agent_run_id' => $run->id,
        'type' => TraceEventType::RunRequested,
        'sequence' => 0,
    ]);
    TraceEvent::factory()->create([
        'agent_run_id' => $run->id,
        'type' => TraceEventType::ModelSelected,
        'sequence' => 1,
    ]);

    $this->get("/{$team->slug}/runs/{$run->slug}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('maacc/runs/show')
            ->where('id', $run->slug)
            ->has('trace', 3)
            ->where('trace.0.type', 'run_requested')
            ->where('trace.1.type', 'model_selected')
            ->where('trace.2.type', 'completed')
        );
});

test('the run detail page renders null trace for an unknown run', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->get("/{$team->slug}/runs/does-not-exist")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('maacc/runs/show')
            ->where('trace', null)
        );
});

test('the run detail trace is scoped to the team', function () {
    [$owner, $team] = ownerAndTeam();
    [$otherOwner, $otherTeam] = ownerAndTeam();

    $run = maaccRun(maaccAgent($otherTeam));
    TraceEvent::factory()->create([
        'agent_run_id' => $run->id,
        'type' => TraceEventType::RunRequested,
        'sequence' => 0,
    ]);

    // The owner of a different team cannot see another team's run trace.
    $this->actingAs($owner)
        ->get("/{$team->slug}/runs/{$run->slug}")
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('maacc/runs/show')
            ->where('trace', null)
        );
});
