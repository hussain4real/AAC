<?php

use App\Enums\CredentialStatus;
use App\Enums\RunStatus;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\Credential;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\ToolContract;
use App\Support\MaacConsoleData;
use Database\Seeders\MaacDemoSeeder;

/**
 * Proves the MAAC demo seeder reproduces the Phase 1 console fixture
 * (resources/js/maac/data.ts) as governed database records.
 */
test('the seeder reproduces the fixture entity counts', function () {
    $this->seed(MaacDemoSeeder::class);

    expect(LlmProvider::count())->toBe(7)
        ->and(Application::count())->toBe(5)
        ->and(Project::count())->toBe(8)
        ->and(ToolContract::count())->toBe(10)
        ->and(Agent::count())->toBe(8)
        ->and(AgentRun::count())->toBe(12)
        ->and(Credential::count())->toBe(5);
});

test('fixture identifiers are preserved as slugs for stable routing', function () {
    $this->seed(MaacDemoSeeder::class);

    expect(Application::whereSlug('MOP')->exists())->toBeTrue()
        ->and(Project::whereSlug('prj_mop_ops')->exists())->toBeTrue()
        ->and(ToolContract::whereSlug('getOperationalRecords')->exists())->toBeTrue()
        ->and(Agent::whereSlug('ag_ops_summary')->exists())->toBeTrue()
        ->and(AgentRun::whereSlug('run_8fa31c')->exists())->toBeTrue()
        ->and(LlmProvider::whereSlug('gpt-4o')->exists())->toBeTrue();

    // UUID primary keys back the slugs.
    expect(Str::isUuid(Application::whereSlug('MOP')->value('id')))->toBeTrue();
});

test('credentials store a hashed secret and never the plaintext', function () {
    $this->seed(MaacDemoSeeder::class);

    $credential = Application::whereSlug('MOP')->first()->credentials()->first();

    expect($credential->secret_hash)->not->toStartWith('maac_sk_')
        ->and($credential->last_four)->toHaveLength(4)
        ->and(Credential::where('secret_hash', 'like', 'maac_sk_%')->count())->toBe(0);
});

test('application credential status reflects the fixture', function () {
    $this->seed(MaacDemoSeeder::class);

    expect(Application::whereSlug('MOP')->first()->credentialStatus())->toBe('Active')
        ->and(Application::whereSlug('VMS')->first()->credentialStatus())->toBe('Revoked')
        ->and(Application::whereSlug('VMS')->first()->credentials()->first()->status)
        ->toBe(CredentialStatus::Revoked);
});

test('the agent and tool assignment graph matches the fixture', function () {
    $this->seed(MaacDemoSeeder::class);

    $agent = Agent::whereSlug('ag_ops_summary')->first();
    expect($agent->tools()->pluck('slug')->sort()->values()->all())
        ->toBe(['getOperationalRecords', 'notifyWorkflowOwner', 'searchPolicyDocuments']);

    $tool = ToolContract::whereSlug('getOperationalRecords')->first();
    expect($tool->agents()->pluck('slug')->sort()->values()->all())
        ->toBe(['ag_doc_review', 'ag_ops_summary']);
});

test('each agent has a current version matching its version label', function () {
    $this->seed(MaacDemoSeeder::class);

    Agent::all()->each(function (Agent $agent) {
        expect($agent->current_version_id)->not->toBeNull()
            ->and($agent->currentVersion)->not->toBeNull()
            ->and($agent->currentVersion->version)->toBe($agent->version);
    });
});

test('runs carry seeded tool calls and trace events', function () {
    $this->seed(MaacDemoSeeder::class);

    $run = AgentRun::whereSlug('run_8fa31c')->first();

    expect($run->toolCalls()->count())->toBe(2)
        ->and($run->traceEvents()->count())->toBeGreaterThan(0)
        ->and($run->latency_ms)->toBe(4200);
});

test('seeded completed runs carry a real final response', function () {
    $this->seed(MaacDemoSeeder::class);

    expect(AgentRun::whereSlug('run_8fa31c')->value('output'))->toContain('Hamad')
        ->and(AgentRun::whereSlug('run_a14d70')->value('output'))->toBeNull(); // waiting_for_client
});

test('the console prop exposes a run\'s real final output', function () {
    [, $team] = ownerAndTeam();
    $agent = maacAgent($team);
    $completed = maacRun($agent, ['status' => RunStatus::Completed, 'output' => 'Berth utilization is 84%.']);
    $waiting = maacRun($agent, ['status' => RunStatus::WaitingForClient, 'output' => null]);

    $data = MaacConsoleData::forTeam($team);

    expect(collect($data['runs'])->firstWhere('id', $completed->slug)['output'])->toBe('Berth utilization is 84%.')
        ->and(collect($data['runs'])->firstWhere('id', $waiting->slug)['output'])->toBeNull();
});

test('the seeder is idempotent', function () {
    $this->seed(MaacDemoSeeder::class);
    $this->seed(MaacDemoSeeder::class);

    expect(Application::count())->toBe(5)
        ->and(Agent::count())->toBe(8)
        ->and(ToolContract::count())->toBe(10)
        ->and(Credential::count())->toBe(5);
});

test('the console prop exposes record uuids and safe credentials for ui wiring', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $model = LlmProvider::factory()->for($team)->create();
    $agent = Agent::factory()->for($project)->for($model)->create();
    $tool = ToolContract::factory()->for($team)->for($application)->create();
    $credential = Credential::factory()->for($application)->create();

    $data = MaacConsoleData::forTeam($team);

    // Each entity exposes its UUID alongside the slug `id`, so the React forms
    // can submit the related-record ids the FormRequests validate against.
    $appData = collect($data['apps'])->firstWhere('id', $application->slug);
    expect($appData['uuid'])->toBe($application->id)
        ->and(collect($data['projects'])->firstWhere('id', $project->slug)['uuid'])->toBe($project->id)
        ->and(collect($data['agents'])->firstWhere('id', $agent->slug)['uuid'])->toBe($agent->id)
        ->and(collect($data['tools'])->firstWhere('id', $tool->slug)['uuid'])->toBe($tool->id)
        ->and(collect($data['llms'])->firstWhere('id', $model->slug)['uuid'])->toBe($model->id);

    // Applications carry safe credential records (no plaintext) so the
    // credentials tab can drive rotate/revoke against a specific id.
    expect($appData['credentials'])->toHaveCount(1);
    $credentialData = $appData['credentials'][0];
    expect($credentialData)->toHaveKeys(['id', 'clientId', 'status', 'environment'])
        ->and($credentialData['id'])->toBe($credential->id)
        ->and($credentialData)->not->toHaveKey('secret');
});
