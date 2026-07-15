<?php

use App\Enums\AgentStatus;
use App\Enums\Environment;
use App\Enums\RoutingStrategy;
use App\Enums\RunStatus;
use App\Enums\Sensitivity;
use App\Enums\TraceEventType;
use App\Exceptions\Sdk\RuntimeRequestException;
use App\Models\AgentRun;
use App\Models\LlmProvider;
use App\Models\ModelRoutingPolicy;
use App\Models\Team;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use App\Support\Runtime\AgentRunner;
use App\Support\Runtime\Routing\ModelRouter;
use App\Support\Runtime\Routing\ProviderHealth;

test('with no routing policy the router selects the agent configured model', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $run = maaccRun($agent, ['environment' => Environment::Production, 'sensitivity' => Sensitivity::Internal]);

    $decision = app(ModelRouter::class)->select($run->load(['agent.routingPolicy', 'agent.llmProvider']));

    expect($decision->provider->is($agent->llmProvider))->toBeTrue()
        ->and($decision->strategy)->toBeNull()
        ->and($decision->rationale)->toContain('No routing policy');
});

test('with no policy an unavailable model yields no selection', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $agent->llmProvider->update(['environments' => [Environment::Staging->value]]);
    $run = maaccRun($agent, ['environment' => Environment::Production, 'sensitivity' => Sensitivity::Internal]);

    $decision = app(ModelRouter::class)->select($run->load(['agent.routingPolicy', 'agent.llmProvider']));

    expect($decision->provider)->toBeNull()
        ->and($decision->rationale)->toContain('not approved or available');
});

test('the router ignores persisted policy candidates outside the project allowlist', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $unapprovedForProject = LlmProvider::factory()->for($team)->create([
        'input_cost' => 0.01,
        'output_cost' => 0.01,
    ]);
    ModelRoutingPolicy::factory()->for($team)->for($agent)->costOptimized()->create([
        'fallback_provider_ids' => [$unapprovedForProject->id],
    ]);
    $run = maaccRun($agent, ['environment' => Environment::Production, 'sensitivity' => Sensitivity::Public]);

    $decision = app(ModelRouter::class)->select($run->load(['agent.routingPolicy', 'agent.llmProvider']));

    expect($decision->provider->is($agent->llmProvider))->toBeTrue()
        ->and($decision->chainIds())->toBe([$agent->llm_provider_id])
        ->and(collect($decision->considered)->pluck('provider.id')->all())
        ->not->toContain($unapprovedForProject->id);
});

test('the runtime rejects a persisted cross-tenant tool assignment before creating a run', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team, ['status' => AgentStatus::Published]);
    $foreignTeam = Team::factory()->create();
    $foreignTool = ToolContract::factory()->for($foreignTeam)->create();
    ToolAssignment::factory()->forAgent($agent)->create(['tool_contract_id' => $foreignTool->id]);

    expect(fn () => app(AgentRunner::class)->start(
        $agent->fresh(),
        $agent->project->application,
        Environment::Production,
        'hi',
        null,
    ))->toThrow(RuntimeRequestException::class, 'not eligible');

    expect(AgentRun::query()->where('agent_id', $agent->id)->exists())->toBeFalse();
});

test('the cost-optimized strategy selects the cheapest eligible model', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $agent->llmProvider->update(['input_cost' => 5, 'output_cost' => 5]);
    $cheap = LlmProvider::factory()->for($team)->create(['input_cost' => 0.5, 'output_cost' => 0.5]);
    $mid = LlmProvider::factory()->for($team)->create(['input_cost' => 2, 'output_cost' => 2]);
    $agent->project->llmProviders()->attach([$cheap->id, $mid->id]);

    ModelRoutingPolicy::factory()->for($team)->for($agent)->costOptimized()->create([
        'fallback_provider_ids' => [$cheap->id, $mid->id],
    ]);

    $run = maaccRun($agent, ['environment' => Environment::Production, 'sensitivity' => Sensitivity::Public]);
    $decision = app(ModelRouter::class)->select($run->load(['agent.routingPolicy', 'agent.llmProvider']));

    expect($decision->provider->is($cheap))->toBeTrue()
        ->and($decision->strategy)->toBe(RoutingStrategy::CostOptimized)
        ->and($decision->chainIds())->toBe([$cheap->id, $mid->id, $agent->llm_provider_id]);
});

test('the router filters candidates by environment, sensitivity, and cost ceiling', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $agent->llmProvider->update(['sensitivity' => Sensitivity::Restricted, 'input_cost' => 1, 'output_cost' => 1]);

    $wrongEnv = LlmProvider::factory()->for($team)->create(['environments' => [Environment::Staging->value], 'sensitivity' => Sensitivity::Restricted]);
    $tooSensitive = LlmProvider::factory()->for($team)->create(['sensitivity' => Sensitivity::Public]);
    $tooExpensive = LlmProvider::factory()->for($team)->create(['sensitivity' => Sensitivity::Restricted, 'input_cost' => 20, 'output_cost' => 20]);
    $agent->project->llmProviders()->attach([$wrongEnv->id, $tooSensitive->id, $tooExpensive->id]);

    ModelRoutingPolicy::factory()->for($team)->for($agent)->create([
        'fallback_provider_ids' => [$wrongEnv->id, $tooSensitive->id, $tooExpensive->id],
        'max_cost_per_1k' => 10,
    ]);

    $run = maaccRun($agent, ['environment' => Environment::Production, 'sensitivity' => Sensitivity::Restricted]);
    $decision = app(ModelRouter::class)->select($run->load(['agent.routingPolicy', 'agent.llmProvider']));

    expect($decision->provider->is($agent->llmProvider))->toBeTrue()
        ->and($decision->eligible)->toHaveCount(1)
        ->and(collect($decision->considered)->firstWhere('provider.id', $wrongEnv->id)->reason)->toContain('environment')
        ->and(collect($decision->considered)->firstWhere('provider.id', $tooSensitive->id)->reason)->toContain('cleared')
        ->and(collect($decision->considered)->firstWhere('provider.id', $tooExpensive->id)->reason)->toContain('cost ceiling');
});

test('the router skips a tenant provider that has no vault credential', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $agent->llmProvider->update(['input_cost' => 5, 'output_cost' => 5]);
    $uncredentialed = LlmProvider::factory()->for($team)->create([
        'input_cost' => 0.01,
        'output_cost' => 0.01,
        'platform_owned' => false,
        'vault_secret_id' => null,
    ]);
    $agent->project->llmProviders()->attach($uncredentialed);
    ModelRoutingPolicy::factory()->for($team)->for($agent)->costOptimized()->create([
        'fallback_provider_ids' => [$uncredentialed->id],
    ]);

    $run = maaccRun($agent, ['environment' => Environment::Production, 'sensitivity' => Sensitivity::Public]);
    $decision = app(ModelRouter::class)->select($run->load(['agent.routingPolicy', 'agent.llmProvider']));

    expect($decision->provider->is($agent->llmProvider))->toBeTrue()
        ->and(collect($decision->considered)->firstWhere('provider.id', $uncredentialed->id)->reason)
        ->toContain('credential source');
});

test('the router skips a provider whose verification is no longer valid', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $agent->llmProvider->update(['input_cost' => 5, 'output_cost' => 5]);
    $unverified = LlmProvider::factory()->for($team)->create([
        'input_cost' => 0.01,
        'output_cost' => 0.01,
        'verification_status' => null,
        'verified_at' => null,
    ]);
    $agent->project->llmProviders()->attach($unverified);
    ModelRoutingPolicy::factory()->for($team)->for($agent)->costOptimized()->create([
        'fallback_provider_ids' => [$unverified->id],
    ]);

    $run = maaccRun($agent, ['environment' => Environment::Production, 'sensitivity' => Sensitivity::Public]);
    $decision = app(ModelRouter::class)->select($run->load(['agent.routingPolicy', 'agent.llmProvider']));

    expect($decision->provider->is($agent->llmProvider))->toBeTrue()
        ->and(collect($decision->considered)->firstWhere('provider.id', $unverified->id)->reason)
        ->toContain('has not passed verification');
});

test('an unhealthy primary is filtered so a healthy fallback is chosen', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $primary = $agent->llmProvider;
    $fallback = LlmProvider::factory()->for($team)->create();
    $agent->project->llmProviders()->attach($fallback);

    // Six recent model-error runs on the primary push it over the failure threshold.
    AgentRun::factory()->count(6)->create([
        'llm_provider_id' => $primary->id,
        'environment' => Environment::Production,
        'status' => RunStatus::Failed,
        'failure_reason' => 'model_error',
        'started_at' => now(),
        'latency_ms' => 900,
    ]);

    ModelRoutingPolicy::factory()->for($team)->for($agent)->create([
        'fallback_provider_ids' => [$fallback->id],
    ]);

    $run = maaccRun($agent, ['environment' => Environment::Production, 'sensitivity' => Sensitivity::Public]);
    $decision = app(ModelRouter::class)->select($run->load(['agent.routingPolicy', 'agent.llmProvider']));

    expect($decision->provider->is($fallback))->toBeTrue()
        ->and(collect($decision->considered)->firstWhere('provider.id', $primary->id)->reason)->toContain('unhealthy');
});

test('provider health reflects recent failures, latency, and an unknown provider', function () {
    [, $team] = ownerAndTeam();
    $provider = LlmProvider::factory()->for($team)->create();
    $idle = LlmProvider::factory()->for($team)->create();

    AgentRun::factory()->count(3)->create(['llm_provider_id' => $provider->id, 'status' => RunStatus::Failed, 'failure_reason' => 'model_error', 'latency_ms' => 1000, 'started_at' => now()]);
    AgentRun::factory()->count(2)->create(['llm_provider_id' => $provider->id, 'status' => RunStatus::Completed, 'failure_reason' => null, 'latency_ms' => 500, 'started_at' => now()]);

    $snapshots = app(ProviderHealth::class)->forProviderIds([$provider->id, $idle->id]);

    expect($snapshots[$provider->id]->sampleSize)->toBe(5)
        ->and($snapshots[$provider->id]->failureRate)->toBe(0.6)
        ->and($snapshots[$provider->id]->healthy)->toBeFalse()
        ->and($snapshots[$provider->id]->avgLatencyMs)->toBe(800)
        ->and($snapshots[$idle->id]->sampleSize)->toBe(0)
        ->and($snapshots[$idle->id]->healthy)->toBeTrue();
});

test('the runtime fails over to the next model when a model call errors mid-run', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team, ['status' => AgentStatus::Published, 'sensitivity' => Sensitivity::Public]);
    // Make the agent's model the cheapest so it is chosen first; failover then
    // moves to the costlier fallback.
    $agent->llmProvider->update(['input_cost' => 0.1, 'output_cost' => 0.1]);
    $fallback = LlmProvider::factory()->for($team)->create(['input_cost' => 9, 'output_cost' => 9]);
    $agent->project->llmProviders()->attach($fallback);

    ModelRoutingPolicy::factory()->for($team)->for($agent)->costOptimized()->create([
        'fallback_provider_ids' => [$fallback->id],
    ]);
    approveCurrentAgentConfiguration($agent);

    $fake = bindFakeRouter();
    $fake->throwThen('primary down')->textThen('Recovered.');

    $run = app(AgentRunner::class)->start($agent->fresh(), $agent->project->application, Environment::Production, 'hi', null);

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($run->llm_provider_id)->toBe($fallback->id)
        ->and($run->traceEvents()->where('type', TraceEventType::ModelFailover)->exists())->toBeTrue();
});

test('the run fails when the routing chain is exhausted', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team, ['status' => AgentStatus::Published, 'sensitivity' => Sensitivity::Public]);

    ModelRoutingPolicy::factory()->for($team)->for($agent)->create(['fallback_provider_ids' => []]);
    approveCurrentAgentConfiguration($agent);

    $fake = bindFakeRouter();
    $fake->throwThen('total outage');

    $run = app(AgentRunner::class)->start($agent->fresh(), $agent->project->application, Environment::Production, 'hi', null);

    expect($run->status)->toBe(RunStatus::Failed)
        ->and($run->failure_reason)->toBe('model_error');
});

test('the run trace records the routing rationale and considered candidates', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team, ['status' => AgentStatus::Published, 'sensitivity' => Sensitivity::Public]);
    $fallback = LlmProvider::factory()->for($team)->create();
    $agent->project->llmProviders()->attach($fallback);
    ModelRoutingPolicy::factory()->for($team)->for($agent)->costOptimized()->create(['fallback_provider_ids' => [$fallback->id]]);
    approveCurrentAgentConfiguration($agent);

    bindFakeRouter()->textThen('done');

    $run = app(AgentRunner::class)->start($agent->fresh(), $agent->project->application, Environment::Production, 'hi', null);

    $selected = $run->traceEvents()->where('type', TraceEventType::ModelSelected)->first();

    expect($selected->data['strategy'])->toBe('cost')
        ->and($selected->data['considered'])->toHaveCount(2)
        ->and($selected->data['rationale'])->toContain('via Cost Optimized');
});
