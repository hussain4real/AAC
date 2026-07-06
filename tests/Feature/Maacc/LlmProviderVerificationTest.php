<?php

use App\Enums\LlmStatus;
use App\Enums\VaultSecretKind;
use App\Models\LlmProvider;
use App\Models\Team;
use App\Support\Runtime\RuntimeAgent;
use App\Support\Secrets\Contracts\SecretVault;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Ai;

/**
 * The console can run a live connection check and can only publish a model once
 * that check passes — so a wrong key or model code never reaches production.
 */
function keyedDraftProvider(Team $team, array $overrides = [], string $key = 'sk-live-key'): LlmProvider
{
    $provider = LlmProvider::factory()->for($team)->draft()->create(array_merge([
        'provider' => 'OpenAI',
        'code' => 'gpt-5.4',
    ], $overrides));

    $secret = app(SecretVault::class)->store(
        $team,
        VaultSecretKind::LlmKey->reference($provider->slug),
        'OpenAI key',
        VaultSecretKind::LlmKey,
        $key,
    );

    $provider->update(['vault_secret_id' => $secret->id]);

    return $provider->fresh();
}

test('verify returns a passing result and records it on the model', function () {
    [$owner, $team] = ownerAndTeam();
    $provider = keyedDraftProvider($team);
    Ai::fakeAgent(RuntimeAgent::class, ['ok']);

    $this->actingAs($owner)
        ->postJson(route('llm-providers.verify', ['current_team' => $team->slug, 'llmProvider' => $provider->slug]))
        ->assertOk()
        ->assertJsonPath('result.passed', true)
        ->assertJsonPath('result.outcome', 'ok')
        ->assertJsonPath('verification.status', 'ok');

    expect($provider->fresh()->isVerified())->toBeTrue();
});

test('verify classifies a wrong model code as a model problem', function () {
    [$owner, $team] = ownerAndTeam();
    $provider = keyedDraftProvider($team, ['code' => 'openai/gpt-5.4']);
    Http::fake(fn () => Http::response(['error' => ['message' => "The model 'openai/gpt-5.4' does not exist."]], 404));

    $this->actingAs($owner)
        ->postJson(route('llm-providers.verify', ['current_team' => $team->slug, 'llmProvider' => $provider->slug]))
        ->assertOk()
        ->assertJsonPath('result.outcome', 'unknown_model')
        ->assertJsonPath('result.focus', 'model')
        ->assertJsonPath('result.passed', false);
});

test('verify is forbidden to a non-admin', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);
    $provider = keyedDraftProvider($team);

    $this->actingAs($member)
        ->postJson(route('llm-providers.verify', ['current_team' => $team->slug, 'llmProvider' => $provider->slug]))
        ->assertForbidden();
});

test('publishing verifies then approves a working model', function () {
    [$owner, $team] = ownerAndTeam();
    $provider = keyedDraftProvider($team);
    Ai::fakeAgent(RuntimeAgent::class, ['ok']);

    $this->actingAs($owner)
        ->post(route('llm-providers.publish', ['current_team' => $team->slug, 'llmProvider' => $provider->slug]))
        ->assertRedirect();

    expect($provider->fresh()->status)->toBe(LlmStatus::Approved);
});

test('publishing refuses a model that fails its connection check', function () {
    [$owner, $team] = ownerAndTeam();
    $provider = keyedDraftProvider($team);
    Http::fake(fn () => Http::response(['error' => ['message' => 'Incorrect API key.']], 401));

    $this->actingAs($owner)
        ->post(route('llm-providers.publish', ['current_team' => $team->slug, 'llmProvider' => $provider->slug]))
        ->assertRedirect();

    expect($provider->fresh()->status)->toBe(LlmStatus::Draft)
        ->and($provider->fresh()->isVerified())->toBeFalse();
});

test('publish is forbidden to a non-admin', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);
    $provider = keyedDraftProvider($team);

    $this->actingAs($member)
        ->post(route('llm-providers.publish', ['current_team' => $team->slug, 'llmProvider' => $provider->slug]))
        ->assertForbidden();
});

test('re-pointing an approved model to a new code unpublishes and clears verification', function () {
    [$owner, $team] = ownerAndTeam();
    // A live, verified, approved model.
    $provider = LlmProvider::factory()->for($team)->create(['provider' => 'OpenAI', 'code' => 'gpt-5.4']);
    expect($provider->status)->toBe(LlmStatus::Approved)
        ->and($provider->isVerified())->toBeTrue();

    $this->actingAs($owner)
        ->put(route('llm-providers.update', ['current_team' => $team->slug, 'llmProvider' => $provider->slug]), [
            'code' => 'gpt-4o',
        ])
        ->assertRedirect();

    $fresh = $provider->fresh();
    expect($fresh->code)->toBe('gpt-4o')
        ->and($fresh->status)->toBe(LlmStatus::Draft)
        ->and($fresh->isVerified())->toBeFalse()
        ->and($fresh->verified_at)->toBeNull();
});

test('the update endpoint cannot approve a model directly', function () {
    [$owner, $team] = ownerAndTeam();
    $provider = LlmProvider::factory()->for($team)->draft()->create();

    $this->actingAs($owner)
        ->put(route('llm-providers.update', ['current_team' => $team->slug, 'llmProvider' => $provider->slug]), [
            'status' => 'approved',
        ])
        ->assertSessionHasErrors('status');

    expect($provider->fresh()->status)->toBe(LlmStatus::Draft);
});

test('creating with an api key stores it in the vault and binds it', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('llm-providers.store', ['current_team' => $team->slug]), [
            'name' => 'GPT-5.4',
            'code' => 'gpt-5.4',
            'provider' => 'OpenAI',
            'context_window' => '400K',
            'input_cost' => 1.25,
            'output_cost' => 10.0,
            'sensitivity' => 'internal',
            'environments' => ['production'],
            'api_key' => 'sk-secret-value-1234',
        ])
        ->assertRedirect();

    $model = LlmProvider::firstWhere('code', 'gpt-5.4');

    expect($model->vault_secret_id)->not->toBeNull()
        ->and($model->vaultSecret->last_four)->toBe('1234')
        ->and($model->resolveApiKey(app(SecretVault::class)))->toBe('sk-secret-value-1234');
});

test('updating with a new api key rotates the secret and clears verification', function () {
    [$owner, $team] = ownerAndTeam();
    $provider = LlmProvider::factory()->for($team)->create(['provider' => 'OpenAI', 'code' => 'gpt-5.4']);
    $secret = app(SecretVault::class)->store(
        $team,
        VaultSecretKind::LlmKey->reference($provider->slug),
        'OpenAI key',
        VaultSecretKind::LlmKey,
        'sk-old-key',
    );
    $provider->update(['vault_secret_id' => $secret->id]);

    $this->actingAs($owner)
        ->put(route('llm-providers.update', ['current_team' => $team->slug, 'llmProvider' => $provider->slug]), [
            'api_key' => 'sk-new-key-value',
        ])
        ->assertRedirect();

    $fresh = $provider->fresh();
    expect($fresh->resolveApiKey(app(SecretVault::class)))->toBe('sk-new-key-value')
        ->and($fresh->verification_status)->toBeNull();
});
