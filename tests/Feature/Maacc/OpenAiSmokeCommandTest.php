<?php

use App\Enums\AgentStatus;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\LlmStatus;
use App\Models\Agent;
use App\Models\LlmProvider;
use App\Models\ToolContract;
use App\Models\VaultSecret;

test('the command provisions a model, two agents, and the sum tool, and binds a vault key', function () {
    [, $team] = ownerAndTeam();

    $this->artisan('maacc:openai-smoke', [
        '--team' => $team->slug,
        '--key' => 'sk-openai-smoke-key',
        '--environment' => 'production',
    ])->assertSuccessful();

    $provider = LlmProvider::firstWhere('code', 'gpt-5.4');
    expect($provider)->not->toBeNull()
        ->and($provider->provider)->toBe('OpenAI')
        ->and($provider->driver())->toBe('openai')
        ->and($provider->status)->toBe(LlmStatus::Approved)
        ->and($provider->environments)->toContain(Environment::Production->value);

    $plain = Agent::firstWhere('agent_slug', 'gpt54-smoke');
    $tooled = Agent::firstWhere('agent_slug', 'gpt54-tool');
    expect($plain->status)->toBe(AgentStatus::Published)
        ->and($plain->llm_provider_id)->toBe($provider->id)
        ->and($tooled->status)->toBe(AgentStatus::Published);

    $vessel = ToolContract::firstWhere('slug', 'vessel_status');
    expect($vessel->execution_mode)->toBe(ExecMode::Hosted)
        ->and($tooled->tools()->pluck('slug')->all())->toContain('vessel_status');

    $secret = VaultSecret::firstWhere('reference', 'llm_key:'.$provider->slug);
    expect($secret)->not->toBeNull()
        ->and($secret->ciphertext)->toBe('sk-openai-smoke-key')
        ->and($provider->fresh()->vault_secret_id)->toBe($secret->id);
});

test('the command falls back to the first team and runs without a key', function () {
    [, $team] = ownerAndTeam();

    $this->artisan('maacc:openai-smoke')->assertSuccessful();

    $provider = LlmProvider::firstWhere('code', 'gpt-5.4');
    expect($provider)->not->toBeNull()
        ->and($provider->team_id)->toBe($team->id)
        ->and($provider->vault_secret_id)->toBeNull();

    expect(VaultSecret::count())->toBe(0);
});

test('the command is idempotent', function () {
    [, $team] = ownerAndTeam();

    $this->artisan('maacc:openai-smoke', ['--team' => $team->slug])->assertSuccessful();
    $this->artisan('maacc:openai-smoke', ['--team' => $team->slug])->assertSuccessful();

    expect(LlmProvider::where('code', 'gpt-5.4')->count())->toBe(1)
        ->and(Agent::where('agent_slug', 'gpt54-smoke')->count())->toBe(1)
        ->and(Agent::firstWhere('agent_slug', 'gpt54-tool')->tools()->count())->toBe(1);
});

test('the command fails when no team exists', function () {
    $this->artisan('maacc:openai-smoke')->assertFailed();
});
