<?php

use App\Enums\Environment;
use App\Enums\MaaccRole;
use App\Enums\RunStatus;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\Evaluation;
use App\Models\EvaluationDataset;
use App\Models\LlmProvider;
use App\Models\ModelRoutingPolicy;
use App\Models\Project;
use App\Models\ToolContract;
use App\Models\User;
use App\Models\WebhookDelivery;
use App\Models\WebhookEndpoint;
use App\Support\MaaccConsolePageData;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

test('console pages receive only their authorized page contract', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    maaccRun($agent);

    $this->actingAs($owner)
        ->get(route('applications', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maacc.apps', 1)
            ->has('maacc.projects', 0)
            ->has('maacc.agents', 0)
            ->has('maacc.runs', 0)
            ->has('maacc.auditEvents', 0));
});

test('runs use bounded cursor pagination and authoritative server filters', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    AgentRun::factory()->count(34)->create([
        'agent_id' => $agent->id,
        'project_id' => $agent->project_id,
        'application_id' => $agent->project->application_id,
        'llm_provider_id' => $agent->llm_provider_id,
        'status' => RunStatus::Completed,
    ]);
    AgentRun::factory()->count(7)->failed()->create([
        'agent_id' => $agent->id,
        'project_id' => $agent->project_id,
        'application_id' => $agent->project->application_id,
        'llm_provider_id' => $agent->llm_provider_id,
    ]);

    $response = $this->actingAs($owner)->get(route('runs', [
        'current_team' => $team->slug,
        'status' => RunStatus::Completed->value,
        'per_page' => 20,
    ]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page
        ->has('maacc.runs', 20)
        ->where('maacc.pagination.runs.count', 20)
        ->where('maacc.pagination.runs.perPage', 20)
        ->where('maacc.pagination.runs.hasMore', true)
        ->where('maacc.runs', fn ($runs): bool => $runs->every(fn (array $run): bool => $run['status'] === RunStatus::Completed->value)));

    expect(strlen($response->getContent()))->toBeLessThan(400_000);
});

test('project actors are scoped in SQL before resources are serialized', function () {
    [, $team] = ownerAndTeam();
    $allowedApplication = Application::factory()->for($team)->create();
    $hiddenApplication = Application::factory()->for($team)->create();
    $allowedProject = Project::factory()->for($allowedApplication)->create();
    $hiddenProject = Project::factory()->for($hiddenApplication)->create();
    $provider = LlmProvider::factory()->for($team)->create();
    $allowedProject->llmProviders()->attach($provider);
    $hiddenProject->llmProviders()->attach($provider);
    $allowedAgent = Agent::factory()->for($allowedProject)->for($provider)->create();
    $hiddenAgent = Agent::factory()->for($hiddenProject)->for($provider)->create();
    maaccRun($allowedAgent);
    maaccRun($hiddenAgent);
    $viewer = projectRoleUser($team, $allowedProject, MaaccRole::Viewer);

    $this->actingAs($viewer)
        ->get(route('runs', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('maacc.runs', 1)
            ->where('maacc.runs.0.projectId', $allowedProject->slug));
});

test('project actors receive only project-scoped dashboard aggregates', function () {
    [$owner, $team] = ownerAndTeam();
    $allowedApplication = Application::factory()->for($team)->create();
    $hiddenApplication = Application::factory()->for($team)->create();
    $allowedProject = Project::factory()->for($allowedApplication)->create();
    $hiddenProject = Project::factory()->for($hiddenApplication)->create();
    $provider = LlmProvider::factory()->for($team)->create();
    $allowedProject->llmProviders()->attach($provider);
    $hiddenProject->llmProviders()->attach($provider);
    $allowedAgent = Agent::factory()->for($allowedProject)->for($provider)->create(['name' => 'Allowed agent']);
    $hiddenAgent = Agent::factory()->for($hiddenProject)->for($provider)->create(['name' => 'Hidden agent']);
    $allowedTool = ToolContract::factory()->for($team)->for($allowedApplication)->create(['name' => 'Allowed application tool']);
    $hiddenTool = ToolContract::factory()->for($team)->for($hiddenApplication)->create(['name' => 'Hidden application tool']);
    maaccRun($allowedAgent, [
        'status' => RunStatus::Completed,
        'tokens_in' => 20,
        'tokens_out' => 10,
        'cost' => 1.25,
        'caller_subject' => 'allowed-user',
        'caller_department' => 'allowed-department',
        'latency_ms' => 1_000,
        'started_at' => now(),
        'created_at' => now(),
    ]);
    maaccRun($hiddenAgent, [
        'status' => RunStatus::Failed,
        'tokens_in' => 5_000,
        'tokens_out' => 500,
        'cost' => 99.99,
        'caller_subject' => 'hidden-user',
        'caller_department' => 'hidden-department',
        'started_at' => now(),
        'created_at' => now(),
    ]);
    $viewer = projectRoleUser($team, $allowedProject, MaaccRole::Viewer);

    expect($viewer->can('view', $allowedTool))->toBeTrue()
        ->and($viewer->can('view', $hiddenTool))->toBeFalse();

    $this->actingAs($owner)
        ->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('maacc.dashboard.stats.runsToday', 2));

    $this->actingAs($viewer)
        ->get(route('dashboard', ['current_team' => $team->slug]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('maacc.dashboard.stats.apps', 1)
            ->where('maacc.dashboard.stats.projects', 1)
            ->where('maacc.dashboard.stats.agents', 1)
            ->where('maacc.dashboard.stats.tools', 1)
            ->where('maacc.dashboard.stats.runsToday', 1)
            ->where('maacc.dashboard.stats.success', 1)
            ->where('maacc.dashboard.stats.failed', 0)
            ->where('maacc.dashboard.stats.tokens', '30')
            ->where('maacc.dashboard.stats.cost', 'USD 1.25')
            ->where('maacc.dashboard.runStatus', fn ($statuses): bool => $statuses->firstWhere('label', 'Completed')['value'] === 1
                && $statuses->firstWhere('label', 'Failed')['value'] === 0)
            ->has('maacc.dashboard.topAgents', 1)
            ->where('maacc.dashboard.topAgents.0.id', $allowedAgent->slug)
            ->where('maacc.dashboard.topAgents.0.runs', 1)
            ->has('maacc.dashboard.usageByUser', 1)
            ->where('maacc.dashboard.usageByUser.0.runs', 1)
            ->where('maacc.dashboard.usageByUser.0.tokens', 30)
            ->where('maacc.dashboard.usageByUser.0.cost', 1.25)
            ->has('maacc.dashboard.usageByDepartment', 1)
            ->where('maacc.dashboard.usageByDepartment.0.runs', 1)
            ->where('maacc.operational.totalRuns', 1)
            ->where('maacc.operational.failedRuns', 0)
            ->where('maacc.operational.avgLatencyMs', 1_000)
            ->has('maacc.dashboard.alerts', 0)
            ->has('maacc.tools', 1)
            ->where('maacc.tools.0.id', $allowedTool->slug)
            ->where('maacc.runs', fn ($runs): bool => $runs->every(
                fn (array $run): bool => $run['projectId'] === $allowedProject->slug
            )));
});

test('the runs page stays within its query and response budgets as rows grow', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    AgentRun::factory()->count(250)->create([
        'agent_id' => $agent->id,
        'project_id' => $agent->project_id,
        'application_id' => $agent->project->application_id,
        'llm_provider_id' => $agent->llm_provider_id,
    ]);
    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = $query->sql;
    });

    $response = $this->actingAs($owner)->get(route('runs', [
        'current_team' => $team->slug,
        'per_page' => 25,
    ]));

    $response->assertOk()->assertInertia(fn (Assert $page) => $page->has('maacc.runs', 25));

    expect(count($queries))->toBeLessThan(45)
        ->and(strlen($response->getContent()))->toBeLessThan(400_000);
});

test('aggregate cache invalidation is tenant scoped and refreshes changed facts', function () {
    [$firstOwner, $firstTeam] = ownerAndTeam();
    [, $secondTeam] = ownerAndTeam();
    $agent = maaccAgent($firstTeam);

    $this->actingAs($firstOwner)
        ->get(route('dashboard', ['current_team' => $firstTeam->slug]))
        ->assertInertia(fn (Assert $page) => $page->where('maacc.dashboard.stats.runsToday', 0));

    $firstKey = "maacc:console:team:{$firstTeam->id}:version";
    $secondKey = "maacc:console:team:{$secondTeam->id}:version";
    $firstVersion = (int) Cache::get($firstKey, 1);
    $secondVersion = (int) Cache::get($secondKey, 1);

    maaccRun($agent, ['started_at' => now()]);

    expect((int) Cache::get($firstKey, 1))->toBeGreaterThan($firstVersion)
        ->and((int) Cache::get($secondKey, 1))->toBe($secondVersion);

    $this->actingAs($firstOwner)
        ->get(route('dashboard', ['current_team' => $firstTeam->slug]))
        ->assertInertia(fn (Assert $page) => $page->where('maacc.dashboard.stats.runsToday', 1));
});

test('page contracts cover filtered detail and project-scoped secondary paths', function () {
    $pageData = app(MaaccConsolePageData::class);
    $requestFor = static function (?User $user, array $query = []): Request {
        $request = Request::create('/console-contract', 'GET', $query);
        $request->setUserResolver(static fn (): ?User => $user);

        return $request;
    };

    expect($pageData->forPage($requestFor(null), 'dashboard')['apps'])->toBe([]);

    [$owner, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $project = Project::factory()->for($application)->create();
    $provider = LlmProvider::factory()->for($team)->create();
    $project->llmProviders()->attach($provider);
    $agent = Agent::factory()->for($project)->for($provider)->create();
    $run = maaccRun($agent, [
        'status' => RunStatus::Completed,
        'environment' => Environment::Production,
    ]);
    $tool = ToolContract::factory()->for($team)->global()->create(['name' => 'Filtered Tool']);
    $endpoint = WebhookEndpoint::factory()->for($application)->create();
    WebhookDelivery::factory()->for($endpoint, 'endpoint')->for($run, 'agentRun')->create();
    $dataset = EvaluationDataset::factory()->for($team)->create(['project_id' => $project->id]);
    Evaluation::factory()->create([
        'team_id' => $team->id,
        'evaluation_dataset_id' => $dataset->id,
        'agent_id' => $agent->id,
    ]);
    ModelRoutingPolicy::factory()->for($team)->for($agent)->create();

    $this->actingAs($owner);
    expect($pageData->forPage($requestFor($owner), 'unknown'))->toMatchArray(['apps' => []])
        ->and($pageData->forPage($requestFor($owner), 'agent', 'missing-agent')['agents'])->toBe([])
        ->and($pageData->forPage($requestFor($owner), 'tool', 'missing-tool')['tools'])->toBe([])
        ->and($pageData->forPage($requestFor($owner), 'run', 'missing-run')['runs'])->toBe([])
        ->and($pageData->forPage($requestFor($owner, [
            'q' => 'Filtered Tool',
            'scope' => 'global',
            'mode' => 'hosted',
        ]), 'tools')['tools'])->toHaveCount(1)
        ->and($pageData->forPage($requestFor($owner, [
            'q' => $run->slug,
            'status' => $run->status->value,
            'environment' => $run->environment->value,
            'application' => $application->slug,
            'agent' => $agent->slug,
        ]), 'runs')['runs'])->toBeArray();

    $viewer = projectRoleUser($team, $project, MaaccRole::Viewer);
    $this->actingAs($viewer);
    $viewerRequest = $requestFor($viewer);

    expect($pageData->forPage($viewerRequest, 'application', $application->slug)['projects'])->toHaveCount(1)
        ->and($pageData->forPage($requestFor($viewer), 'evaluations')['evaluations'])->toHaveCount(1)
        ->and($pageData->forPage($requestFor($viewer), 'governance'))->toHaveKey('auditEvents')
        ->and($pageData->forPage($requestFor($viewer), 'webhooks')['webhooks'])->toHaveCount(1)
        ->and($pageData->forPage($requestFor($viewer), 'routing')['routingPolicies'])->toHaveCount(1)
        ->and($pageData->forPage($requestFor($viewer), 'identity')['ssoConnections'])->toBe([]);

    expect($tool->exists)->toBeTrue();
});

test('runs meet high volume query response latency and memory budgets', function () {
    [$owner, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $rowCount = max(10_000, (int) env('MAACC_PERFORMANCE_ROWS', 10_000));
    $now = now()->toDateTimeString();

    for ($offset = 0; $offset < $rowCount; $offset += 1_000) {
        $rows = [];
        $limit = min($offset + 1_000, $rowCount);

        for ($index = $offset; $index < $limit; $index++) {
            $rows[] = [
                'id' => (string) Str::uuid(),
                'agent_id' => $agent->id,
                'project_id' => $agent->project_id,
                'application_id' => $agent->project->application_id,
                'llm_provider_id' => $agent->llm_provider_id,
                'slug' => "benchmark_run_{$index}",
                'caller' => 'benchmark',
                'status' => RunStatus::Completed->value,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('agent_runs')->insert($rows);
    }

    $queryCount = 0;
    DB::listen(function () use (&$queryCount): void {
        $queryCount++;
    });
    $durations = [];
    $payloadBytes = [];
    $peakBefore = memory_get_peak_usage(true);

    for ($sample = 0; $sample < 20; $sample++) {
        $queryCount = 0;
        $started = hrtime(true);
        $response = $this->actingAs($owner)->get(route('runs', [
            'current_team' => $team->slug,
            'status' => RunStatus::Completed->value,
            'per_page' => 25,
        ]));
        $durations[] = (hrtime(true) - $started) / 1_000_000;
        $payloadBytes[] = strlen($response->getContent());

        $response->assertOk()->assertInertia(
            fn (Assert $page) => $page->has('maacc.runs', 25),
        );
        expect($queryCount)->toBeLessThanOrEqual(45);
    }

    sort($durations);
    $percentile = static fn (float $value): float => $durations[
        min(count($durations) - 1, (int) ceil(count($durations) * $value) - 1)
    ];
    $plan = DB::select(
        'EXPLAIN QUERY PLAN SELECT * FROM agent_runs WHERE application_id = ? AND status = ? ORDER BY created_at DESC LIMIT 25',
        [$agent->project->application_id, RunStatus::Completed->value],
    );
    $planText = collect($plan)->map(fn (object $row): string => implode(' ', (array) $row))->join(' ');

    expect(max($payloadBytes))->toBeLessThanOrEqual(400_000)
        ->and($percentile(0.50))->toBeLessThanOrEqual(750.0)
        ->and($percentile(0.95))->toBeLessThanOrEqual(1_500.0)
        ->and($percentile(0.99))->toBeLessThanOrEqual(2_000.0)
        ->and(memory_get_peak_usage(true) - $peakBefore)->toBeLessThanOrEqual(128 * 1024 * 1024)
        ->and($planText)->toContain('agent_runs_app_status_created_index');
})->group('performance');
