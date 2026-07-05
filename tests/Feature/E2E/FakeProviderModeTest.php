<?php

use App\Enums\RunStatus;
use App\Models\Agent;
use App\Models\Application;
use App\Models\Credential;
use App\Support\Runtime\AiLlmRouter;
use App\Support\Runtime\Contracts\LlmRouter;
use App\Support\Runtime\DeterministicLlmRouter;
use Database\Seeders\MaaccE2ESeeder;
use Illuminate\Support\Facades\Log;
use Laravel\Passport\Passport;

/**
 * Phase 6A fake-provider mode: the deterministic LLM router lets the validation
 * harness run the complete lifecycle with no external model spend or network
 * dependency, selected entirely by the `maacc.runtime.driver` config flag.
 */
test('the default driver binds the production AI router', function () {
    expect(app(LlmRouter::class))->toBeInstanceOf(AiLlmRouter::class);
});

test('the fake driver binds the deterministic router', function () {
    config(['maacc.runtime.driver' => 'fake']);
    app()->forgetInstance(LlmRouter::class);

    expect(app(LlmRouter::class))->toBeInstanceOf(DeterministicLlmRouter::class);
});

test('a stray fake driver self-heals to the AI router in production', function () {
    Log::spy();
    config(['maacc.runtime.driver' => 'fake']);
    app()->detectEnvironment(fn (): string => 'production');
    app()->forgetInstance(LlmRouter::class);

    expect(app()->isProduction())->toBeTrue()
        ->and(app(LlmRouter::class))->toBeInstanceOf(AiLlmRouter::class);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message): bool => str_contains($message, 'MAACC_LLM_DRIVER=fake'))
        ->once();
});

test('the fake provider mode drives a full pause, resume, and completion with no scripted router', function () {
    config(['maacc.runtime.driver' => 'fake']);

    $this->seed(MaaccE2ESeeder::class);
    $application = Application::firstWhere('slug', MaaccE2ESeeder::APP_SLUG);
    $agent = Agent::firstWhere('agent_slug', MaaccE2ESeeder::AGENT_SLUG);
    $credential = Credential::query()
        ->where('application_id', $application->id)
        ->where('label', MaaccE2ESeeder::CREDENTIAL_LABEL)
        ->first();
    Passport::actingAsClient($credential->oauthClient, [], 'api');

    // No bindFakeRouter(): the container's `fake` driver supplies the router.
    $start = $this->postJson("/api/v1/agents/{$agent->agent_slug}/runs", ['input' => 'Summarize today.'])
        ->assertCreated()
        ->assertJsonPath('status', RunStatus::WaitingForClient->value);

    // The deterministic router synthesized a schema-valid payload from the tool.
    expect($start->json('tool_call.tool'))->toBe(MaaccE2ESeeder::TOOL_SLUG)
        ->and($start->json('tool_call.arguments'))->toBe(['query' => 'e2e']);

    $this->postJson("/api/v1/runs/{$start->json('run_id')}/tool-results", [
        'tool_call_id' => $start->json('tool_call.id'),
        'result' => ['records' => ['a'], 'total' => 1],
    ])
        ->assertOk()
        ->assertJsonPath('status', RunStatus::Completed->value)
        ->assertJsonPath('response', 'Deterministic agent response.');
});
