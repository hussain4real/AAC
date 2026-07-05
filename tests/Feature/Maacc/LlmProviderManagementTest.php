<?php

use App\Enums\LlmStatus;
use App\Models\LlmProvider;

test('a platform admin can add a model to the catalog', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('llm-providers.store', ['current_team' => $team->slug]), [
            'name' => 'GPT-4o',
            'code' => 'azure/gpt-4o',
            'provider' => 'Azure OpenAI',
            'context_window' => '128K',
            'input_cost' => 2.5,
            'output_cost' => 10.0,
            'sensitivity' => 'restricted',
            'environments' => ['production', 'staging'],
        ])
        ->assertRedirect();

    $model = LlmProvider::firstWhere('code', 'azure/gpt-4o');

    expect($model)->not->toBeNull()
        ->and($model->team_id)->toBe($team->id)
        ->and($model->status)->toBe(LlmStatus::Approved);
});

test('model catalog creation validates required fields', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('llm-providers.store', ['current_team' => $team->slug]), [])
        ->assertSessionHasErrors(['name', 'code', 'provider', 'context_window', 'input_cost', 'output_cost', 'sensitivity', 'environments']);
});

test('a non-admin cannot manage the model catalog', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);

    $this->actingAs($member)
        ->post(route('llm-providers.store', ['current_team' => $team->slug]), [
            'name' => 'X', 'code' => 'x/y', 'provider' => 'P', 'context_window' => '8K',
            'input_cost' => 1, 'output_cost' => 1, 'sensitivity' => 'internal', 'environments' => ['development'],
        ])
        ->assertForbidden();
});

test('a platform admin can update a catalog model', function () {
    [$owner, $team] = ownerAndTeam();
    $model = LlmProvider::factory()->for($team)->create();

    $this->actingAs($owner)
        ->put(route('llm-providers.update', ['current_team' => $team->slug, 'llmProvider' => $model->slug]), [
            'status' => 'deprecated',
        ])
        ->assertRedirect();

    expect($model->fresh()->status)->toBe(LlmStatus::Deprecated);
});
