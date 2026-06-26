<?php

use App\Enums\ExecMode;
use App\Http\Resources\Maac\AgentResource;
use App\Models\Agent;
use App\Models\Application;
use App\Models\Project;
use App\Models\ToolAssignment;
use App\Models\ToolContract;

test('the agent console resource exposes the effective prompt with the MAAC tool brief', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $agent = Agent::factory()->for($project)->create([
        'system_prompt' => 'You summarize operations.',
    ]);
    $tool = ToolContract::factory()->for($team)->for($application)->create([
        'slug' => 'get_records',
        'name' => 'Get Records',
        'description' => 'Fetches operational voyage records.',
        'execution_mode' => ExecMode::Knowledge,
    ]);
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $tool->id]);

    $payload = AgentResource::make($agent->load('tools'))->resolve();

    expect($payload['effectivePrompt'])
        ->toContain('You summarize operations.')
        ->toContain('## Tools available to you')
        ->toContain('`get_records` (Get Records)')
        ->toContain('Fetches operational voyage records.');
});

test('the agent console resource falls back to the user prompt when no tools are loaded', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $agent = Agent::factory()->for($project)->create([
        'system_prompt' => 'You summarize operations.',
    ]);

    $payload = AgentResource::make($agent)->resolve();

    expect($payload['effectivePrompt'])->toBe('You summarize operations.');
});
