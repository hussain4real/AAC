<?php

use App\Enums\AgentStatus;
use App\Enums\Environment;
use App\Enums\RunStatus;
use App\Enums\VaultSecretKind;
use App\Models\LlmProvider;
use App\Models\VaultSecret;
use App\Support\Runtime\AgentRunner;
use Inertia\Testing\AssertableInertia as Assert;

test('the vault console page renders', function () {
    [$owner, $team] = ownerAndTeam();

    $this->withoutVite()
        ->actingAs($owner)
        ->get(route('vault', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('maacc/vault'));
});

test('a platform admin can store a generic secret', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('vault-secrets.store', ['current_team' => $team->slug]), [
            'name' => 'Partner API token',
            'kind' => 'generic',
            'value' => 'tok-abcdef-123456',
        ])
        ->assertRedirect();

    $secret = VaultSecret::firstWhere('name', 'Partner API token');

    expect($secret)->not->toBeNull()
        ->and($secret->team_id)->toBe($team->id)
        ->and($secret->kind)->toBe(VaultSecretKind::Generic)
        ->and($secret->last_four)->toBe('3456')
        ->and($secret->ciphertext)->toBe('tok-abcdef-123456')
        ->and($secret->getRawOriginal('ciphertext'))->not->toBe('tok-abcdef-123456')
        ->and($secret->created_by)->toBe($owner->getAuthIdentifier());
});

test('storing an LLM key binds it to the chosen model', function () {
    [$owner, $team] = ownerAndTeam();
    $provider = LlmProvider::factory()->for($team)->create();

    $this->actingAs($owner)
        ->post(route('vault-secrets.store', ['current_team' => $team->slug]), [
            'name' => 'Anthropic prod key',
            'kind' => 'llm_key',
            'value' => 'sk-ant-prod-0001',
            'llm_provider_id' => $provider->id,
        ])
        ->assertRedirect();

    $secret = VaultSecret::firstWhere('name', 'Anthropic prod key');

    expect($provider->fresh()->vault_secret_id)->toBe($secret->id)
        ->and($secret->reference)->toBe('llm_key:'.$provider->slug);
});

test('storing an LLM key requires a model binding', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('vault-secrets.store', ['current_team' => $team->slug]), [
            'name' => 'Dangling key',
            'kind' => 'llm_key',
            'value' => 'sk-ant-dangling',
        ])
        ->assertSessionHasErrors(['llm_provider_id']);
});

test('storing a secret validates name, kind, and value', function () {
    [$owner, $team] = ownerAndTeam();

    $this->actingAs($owner)
        ->post(route('vault-secrets.store', ['current_team' => $team->slug]), [
            'name' => '',
            'kind' => 'not-a-kind',
            'value' => '',
        ])
        ->assertSessionHasErrors(['name', 'kind', 'value']);
});

test('a plain member cannot manage the vault', function () {
    [, $team] = ownerAndTeam();
    $member = teamMember($team);

    $this->actingAs($member)
        ->post(route('vault-secrets.store', ['current_team' => $team->slug]), [
            'name' => 'Blocked',
            'kind' => 'generic',
            'value' => 'nope',
        ])
        ->assertForbidden();
});

test('a platform admin can rotate a secret', function () {
    [$owner, $team] = ownerAndTeam();
    $secret = VaultSecret::factory()->for($team)->withValue('old-value')->create();

    $this->actingAs($owner)
        ->post(route('vault-secrets.rotate', ['current_team' => $team->slug, 'vaultSecret' => $secret->id]), [
            'value' => 'fresh-value',
        ])
        ->assertRedirect();

    $fresh = $secret->fresh();
    expect($fresh->version)->toBe(2)
        ->and($fresh->ciphertext)->toBe('fresh-value')
        ->and($fresh->rotated_at)->not->toBeNull();
});

test('forgetting a secret unbinds the models that used it', function () {
    [$owner, $team] = ownerAndTeam();
    $secret = VaultSecret::factory()->for($team)->llmKey()->create();
    $provider = LlmProvider::factory()->for($team)->create(['vault_secret_id' => $secret->id]);

    $this->actingAs($owner)
        ->delete(route('vault-secrets.destroy', ['current_team' => $team->slug, 'vaultSecret' => $secret->id]))
        ->assertRedirect();

    expect($secret->fresh()->trashed())->toBeTrue()
        ->and($provider->fresh()->vault_secret_id)->toBeNull();
});

test('the console vault dataset exposes secrets without the plaintext', function () {
    [$owner, $team] = ownerAndTeam();
    VaultSecret::factory()->for($team)->withValue('super-secret-value')->create(['name' => 'Ops token']);

    $this->actingAs($owner)
        ->get(route('applications', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maacc.vaultSecrets', 1)
            ->where('maacc.vaultSecrets.0.name', 'Ops token')
            ->where('maacc.vaultSecrets.0.lastFour', 'alue')
            ->where('maacc.vaultSecrets.0.version', 1)
            ->missing('maacc.vaultSecrets.0.ciphertext')
            ->missing('maacc.vaultSecrets.0.value'));
});

test('the runtime resolves the model API key from the vault and records the access', function () {
    [, $team] = ownerAndTeam();
    $secret = VaultSecret::factory()->for($team)->llmKey()->withValue('sk-runtime-vault-key')->create();

    $agent = maaccAgent($team, ['status' => AgentStatus::Published]);
    $agent->llmProvider->update(['vault_secret_id' => $secret->id]);
    approveCurrentAgentConfiguration($agent);

    $fake = bindFakeRouter();
    $fake->textThen('All set.');

    $run = app(AgentRunner::class)->start(
        $agent->fresh(),
        $agent->project->application,
        Environment::Production,
        'hello',
        null,
    );

    expect($run->status)->toBe(RunStatus::Completed)
        ->and($fake->requests[0]->apiKey)->toBe('sk-runtime-vault-key')
        ->and($secret->fresh()->accessed_count)->toBeGreaterThan(0);
});
