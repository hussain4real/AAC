<?php

use App\Enums\RunStatus;
use App\Models\Agent;
use App\Models\Application;
use App\Models\Credential;
use App\Support\Runtime\AiLlmRouter;
use App\Support\Runtime\Contracts\LlmRouter;
use App\Support\Runtime\DeterministicLlmRouter;
use Database\Seeders\MaacE2ESeeder;
use Laravel\Passport\Passport;

/**
 * Phase 6A fake-provider mode: the deterministic LLM router lets the validation
 * harness run the complete lifecycle with no external model spend or network
 * dependency, selected entirely by the `maac.runtime.driver` config flag.
 */
test('the default driver binds the production AI router', function () {
    expect(app(LlmRouter::class))->toBeInstanceOf(AiLlmRouter::class);
});

test('the fake driver binds the deterministic router', function () {
    config(['maac.runtime.driver' => 'fake']);
    app()->forgetInstance(LlmRouter::class);

    expect(app(LlmRouter::class))->toBeInstanceOf(DeterministicLlmRouter::class);
});

test('the fake provider mode drives a full pause, resume, and completion with no scripted router', function () {
    config(['maac.runtime.driver' => 'fake']);

    $this->seed(MaacE2ESeeder::class);
    $application = Application::firstWhere('slug', MaacE2ESeeder::APP_SLUG);
    $agent = Agent::firstWhere('agent_slug', MaacE2ESeeder::AGENT_SLUG);
    $credential = Credential::query()
        ->where('application_id', $application->id)
        ->where('label', MaacE2ESeeder::CREDENTIAL_LABEL)
        ->first();
    Passport::actingAsClient($credential->oauthClient, [], 'api');

    // No bindFakeRouter(): the container's `fake` driver supplies the router.
    $start = $this->postJson("/api/v1/agents/{$agent->agent_slug}/runs", ['input' => 'Summarize today.'])
        ->assertCreated()
        ->assertJsonPath('status', RunStatus::WaitingForClient->value);

    // The deterministic router synthesized a schema-valid payload from the tool.
    expect($start->json('tool_call.tool'))->toBe(MaacE2ESeeder::TOOL_SLUG)
        ->and($start->json('tool_call.arguments'))->toBe(['query' => 'e2e']);

    $this->postJson("/api/v1/runs/{$start->json('run_id')}/tool-results", [
        'tool_call_id' => $start->json('tool_call.id'),
        'result' => ['records' => ['a'], 'total' => 1],
    ])
        ->assertOk()
        ->assertJsonPath('status', RunStatus::Completed->value)
        ->assertJsonPath('response', 'Deterministic agent response.');
});
