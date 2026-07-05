<?php

use App\Enums\AgentStatus;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Enums\LlmStatus;
use App\Models\Agent;
use App\Models\Application;
use App\Models\Credential;
use App\Models\LlmProvider;
use App\Models\Team;
use App\Models\ToolContract;
use App\Models\VaultSecret;

/**
 * Build a team-scoped OpenAI gpt-5.4 model with a bound vault key, which the
 * Node client demo command requires.
 */
function vaultKeyedGpt54(Team $team): LlmProvider
{
    $secret = VaultSecret::factory()->for($team)->llmKey()->create();

    return LlmProvider::factory()->for($team)->create([
        'code' => 'gpt-5.4',
        'provider' => 'OpenAI',
        'status' => LlmStatus::Approved,
        'environments' => [Environment::Production->value],
        'vault_secret_id' => $secret->id,
    ]);
}

test('the command provisions an app, a credential, and a client-side tool agent', function () {
    [, $team] = ownerAndTeam();
    vaultKeyedGpt54($team);

    $this->artisan('maacc:node-client-demo', ['--team' => $team->slug])->assertSuccessful();

    $application = Application::firstWhere('slug', 'node-test-client');
    expect($application)->not->toBeNull()
        ->and($application->team_id)->toBe($team->id);

    $tool = ToolContract::firstWhere('slug', 'fetch_port_records');
    expect($tool->execution_mode)->toBe(ExecMode::Client)
        ->and($tool->implementation_status)->toBe(ImplStatus::Required)
        ->and($tool->application_id)->toBe($application->id);

    $agent = Agent::firstWhere('agent_slug', 'node-port-ops');
    expect($agent->status)->toBe(AgentStatus::Published)
        ->and($agent->tools()->pluck('slug')->all())->toContain('fetch_port_records');

    $credential = Credential::firstWhere('application_id', $application->id);
    expect($credential)->not->toBeNull()
        ->and($credential->client_id)->not->toBeNull()
        ->and($credential->oauth_client_id)->not->toBeNull();
});

test('the command fails when no vault-keyed model is available', function () {
    [, $team] = ownerAndTeam();

    $this->artisan('maacc:node-client-demo', ['--team' => $team->slug])->assertFailed();
});

test('the command fails when no team exists', function () {
    $this->artisan('maacc:node-client-demo')->assertFailed();
});

test('the command fails when the team has no member to own the credential', function () {
    $team = Team::factory()->create();

    $this->artisan('maacc:node-client-demo', ['--team' => $team->slug])->assertFailed();
});
