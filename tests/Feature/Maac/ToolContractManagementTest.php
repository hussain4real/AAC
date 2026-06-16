<?php

use App\Enums\ImplStatus;
use App\Enums\MaacRole;
use App\Models\Application;
use App\Models\Project;
use App\Models\ToolContract;

test('a developer can create a client-side tool contract', function () {
    [, $team] = ownerAndTeam();
    $developer = teamMember($team);
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $project->members()->attach($developer, ['maac_role' => MaacRole::Developer->value]);

    $this->actingAs($developer)
        ->post(route('tools.store', ['current_team' => $team->slug]), [
            'name' => 'getOperationalRecords',
            'scope' => 'project',
            'execution_mode' => 'client',
            'sensitivity' => 'confidential',
            'timeout_seconds' => 15,
            'max_payload_kb' => 256,
            'input_schema' => ['from_date' => 'string', 'to_date' => 'string'],
            'output_schema' => ['summary' => 'object', 'records' => 'array'],
        ])
        ->assertRedirect();

    $tool = ToolContract::firstWhere('name', 'getOperationalRecords');

    expect($tool)->not->toBeNull()
        ->and($tool->team_id)->toBe($team->id)
        ->and($tool->implementation_status)->toBe(ImplStatus::Required);
});

test('a global tool contract defaults to ready implementation status', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)->post(route('tools.store', ['current_team' => $team->slug]), [
        'name' => 'webSearch',
        'scope' => 'global',
        'execution_mode' => 'hosted',
        'sensitivity' => 'public',
        'timeout_seconds' => 10,
        'max_payload_kb' => 256,
        'input_schema' => ['query' => 'string'],
        'output_schema' => ['results' => 'array'],
    ])->assertRedirect();

    expect(ToolContract::firstWhere('name', 'webSearch')->implementation_status)->toBe(ImplStatus::Ready);
});

test('tool contract creation requires valid JSON input and output schemas', function () {
    [$owner, $team] = ownerAndTeam();

    // Missing schemas entirely.
    $this->actingAs($owner)->post(route('tools.store', ['current_team' => $team->slug]), [
        'name' => 'badTool',
        'scope' => 'global',
        'execution_mode' => 'hosted',
        'sensitivity' => 'public',
        'timeout_seconds' => 10,
        'max_payload_kb' => 256,
    ])->assertSessionHasErrors(['input_schema', 'output_schema']);

    // Schema must be an object of field => type, not a scalar string.
    $this->actingAs($owner)->post(route('tools.store', ['current_team' => $team->slug]), [
        'name' => 'badTool',
        'scope' => 'global',
        'execution_mode' => 'hosted',
        'sensitivity' => 'public',
        'timeout_seconds' => 10,
        'max_payload_kb' => 256,
        'input_schema' => 'not-an-object',
        'output_schema' => ['results' => 'array'],
    ])->assertSessionHasErrors('input_schema');
});

test('tool contract creation validates metadata bounds', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)->post(route('tools.store', ['current_team' => $team->slug]), [
        'name' => 'slowTool',
        'scope' => 'global',
        'execution_mode' => 'hosted',
        'sensitivity' => 'public',
        'timeout_seconds' => 9000,
        'max_payload_kb' => 99999999,
        'input_schema' => ['q' => 'string'],
        'output_schema' => ['r' => 'array'],
    ])->assertSessionHasErrors(['timeout_seconds', 'max_payload_kb']);
});

test('a plain member cannot create a tool contract', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);

    $this->actingAs($member)->post(route('tools.store', ['current_team' => $team->slug]), [
        'name' => 'blockedTool',
        'scope' => 'global',
        'execution_mode' => 'hosted',
        'sensitivity' => 'public',
        'timeout_seconds' => 10,
        'max_payload_kb' => 256,
        'input_schema' => ['q' => 'string'],
        'output_schema' => ['r' => 'array'],
    ])->assertForbidden();
});

test('a platform admin can update a tool contract', function () {
    [$owner, $team] = ownerAndTeam();
    $tool = ToolContract::factory()->for($team)->create();

    $this->actingAs($owner)
        ->put(route('tools.update', ['current_team' => $team->slug, 'tool' => $tool->slug]), [
            'description' => 'Updated description.',
        ])
        ->assertRedirect();

    expect($tool->fresh()->description)->toBe('Updated description.');
});
