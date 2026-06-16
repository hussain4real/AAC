<?php

use App\Enums\CredentialStatus;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\Credential;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\ToolContract;
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

test('the seeder is idempotent', function () {
    $this->seed(MaacDemoSeeder::class);
    $this->seed(MaacDemoSeeder::class);

    expect(Application::count())->toBe(5)
        ->and(Agent::count())->toBe(8)
        ->and(ToolContract::count())->toBe(10)
        ->and(Credential::count())->toBe(5);
});
