<?php

use App\Actions\Maacc\CreateModelRoutingPolicy;
use App\Enums\RoutingStrategy;
use App\Models\LlmProvider;
use App\Models\ModelRoutingPolicy;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

test('the routing console page renders', function () {
    [$owner, $team] = ownerAndTeam();

    $this->withoutVite()
        ->actingAs($owner)
        ->get(route('routing', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('maacc/routing'));
});

test('a platform admin can create a routing policy', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $fallback = LlmProvider::factory()->for($team)->create();
    $agent->project->llmProviders()->attach($fallback);

    $this->actingAs($owner)
        ->post(route('routing-policies.store', ['current_team' => $team->slug]), [
            'name' => 'Tiered routing',
            'agent_id' => $agent->id,
            'strategy' => 'cost',
            'fallback_provider_ids' => [$fallback->id],
            'max_cost_per_1k' => 8.5,
            'max_latency_ms' => 4000,
        ])
        ->assertRedirect();

    $policy = ModelRoutingPolicy::firstWhere('name', 'Tiered routing');

    expect($policy)->not->toBeNull()
        ->and($policy->team_id)->toBe($team->id)
        ->and($policy->agent_id)->toBe($agent->id)
        ->and($policy->strategy)->toBe(RoutingStrategy::CostOptimized)
        ->and($policy->fallback_provider_ids)->toBe([$fallback->id])
        ->and($policy->max_cost_per_1k)->toBe(8.5)
        ->and($policy->created_by)->toBe($owner->getAuthIdentifier());
});

test('routing policy creation validates and enforces one policy per agent', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    ModelRoutingPolicy::factory()->for($team)->for($agent)->create();

    $this->actingAs($owner)
        ->post(route('routing-policies.store', ['current_team' => $team->slug]), [
            'name' => '',
            'agent_id' => $agent->id,
            'strategy' => 'invalid',
        ])
        ->assertSessionHasErrors(['name', 'agent_id', 'strategy']);
});

test('routing policy candidates must be approved for the agent project at every write boundary', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $unapprovedForProject = LlmProvider::factory()->for($team)->create();
    $payload = [
        'name' => 'Unsafe fallback',
        'agent_id' => $agent->id,
        'strategy' => 'balanced',
        'fallback_provider_ids' => [$unapprovedForProject->id],
    ];

    $this->actingAs($owner)
        ->post(route('routing-policies.store', ['current_team' => $team->slug]), $payload)
        ->assertSessionHasErrors(['fallback_provider_ids.0']);

    expect(fn () => app(CreateModelRoutingPolicy::class)->handle(
        $team,
        $payload,
        $owner->getAuthIdentifier(),
    ))->toThrow(ValidationException::class);

    expect(ModelRoutingPolicy::query()->where('agent_id', $agent->id)->exists())->toBeFalse();
});

test('a plain member cannot manage routing policies', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $member = teamMember($team);

    $this->actingAs($member)
        ->post(route('routing-policies.store', ['current_team' => $team->slug]), [
            'name' => 'Blocked',
            'agent_id' => $agent->id,
            'strategy' => 'balanced',
        ])
        ->assertForbidden();
});

test('a platform admin can update and delete a routing policy', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $policy = ModelRoutingPolicy::factory()->for($team)->for($agent)->create(['enabled' => true]);

    $this->actingAs($owner)
        ->put(route('routing-policies.update', ['current_team' => $team->slug, 'modelRoutingPolicy' => $policy->id]), [
            'strategy' => 'latency',
            'enabled' => false,
        ])
        ->assertRedirect();

    expect($policy->fresh()->strategy)->toBe(RoutingStrategy::LatencyOptimized)
        ->and($policy->fresh()->enabled)->toBeFalse();

    $this->actingAs($owner)
        ->delete(route('routing-policies.destroy', ['current_team' => $team->slug, 'modelRoutingPolicy' => $policy->id]))
        ->assertRedirect();

    expect(ModelRoutingPolicy::find($policy->id))->toBeNull();
});

test('the routing page exposes policies and provider health', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    ModelRoutingPolicy::factory()->for($team)->for($agent)->costOptimized()->create(['name' => 'Cheap first']);

    $this->actingAs($owner)
        ->get(route('routing', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maacc.routingPolicies', 1)
            ->where('maacc.routingPolicies.0.name', 'Cheap first')
            ->where('maacc.routingPolicies.0.strategy', 'cost')
            ->where('maacc.routingPolicies.0.agentName', $agent->name)
            ->has('maacc.providerHealth')
            ->where('maacc.providerHealth.0.healthy', true));
});
