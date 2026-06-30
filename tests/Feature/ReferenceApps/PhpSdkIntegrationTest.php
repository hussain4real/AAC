<?php

use App\Actions\Maac\CreateCredential;
use App\Enums\TraceEventType;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\ToolImplementation;
use App\Models\User;
use Database\Seeders\MaacE2ESeeder;
use Illuminate\Support\Facades\Artisan;
use Maac\Reference\Laravel\Handlers\FetchRecordsHandler;
use Maac\Reference\Laravel\Support\CargoRepository;
use Maac\Sdk\MaacClient;
use Maac\Sdk\MaacConfig;
use Maac\Sdk\Tools\ToolHandlerRegistry;
use Tests\Support\Sdk\KernelTransport;

/**
 * Phase 6B: an external application drives the entire MAAC integration through
 * the public SDK only — exchanging a real client_credentials token, syncing the
 * manifest, reporting a local handler, invoking the agent, and servicing the
 * client-side tool pause/resume — using the framework-agnostic maac/sdk
 * against a seeded MAAC instance over the in-process kernel transport.
 */
beforeEach(function () {
    if (! file_exists(storage_path('oauth-private.key'))) {
        Artisan::call('passport:keys');
    }

    $this->seed(MaacE2ESeeder::class);
    $this->application = Application::firstWhere('slug', MaacE2ESeeder::APP_SLUG);
    $owner = User::firstWhere('email', MaacE2ESeeder::USER_EMAIL);

    $issued = app(CreateCredential::class)->handle($this->application, $owner, ['environment' => 'production']);
    $this->client = new MaacClient(
        new MaacConfig('', $issued->credential->client_id, $issued->plainSecret),
        new KernelTransport($this),
    );
});

it('completes the full SDK lifecycle from token to a finished run', function () {
    // 1. Token exchange (client_credentials).
    expect($this->client->authenticate())->not->toBe('');

    // 2. Manifest sync — the tool is listed and reported as needing a handler.
    $manifest = $this->client->manifest();
    $tool = $manifest->tool(MaacE2ESeeder::TOOL_SLUG);
    expect($manifest->agent(MaacE2ESeeder::AGENT_SLUG))->not->toBeNull()
        ->and($tool)->not->toBeNull()
        ->and($tool->implementationStatus())->toBe('required');

    // 3. Report the local handler — MAAC reconciles it as implemented.
    $registry = (new ToolHandlerRegistry)->register(
        new FetchRecordsHandler(new CargoRepository, MaacE2ESeeder::TOOL_SLUG),
    );
    $results = $this->client->reportHandlers($manifest, $registry, 'php');
    expect($results[0]['tool'])->toBe(MaacE2ESeeder::TOOL_SLUG)
        ->and($results[0]['status'])->toBe('implemented');

    // 4. The manifest now reports the tool implemented (the key transition).
    expect($this->client->manifest()->tool(MaacE2ESeeder::TOOL_SLUG)?->isImplemented())->toBeTrue();

    // 5. Invoke the agent — the model requests the client tool, the SDK services
    //    it from the registry, submits the result, and the run completes.
    bindFakeRouter()
        ->toolCallThen(MaacE2ESeeder::TOOL_SLUG, ['query' => 'today'])
        ->textThen('All berths are clear.');

    $run = $this->client->run(MaacE2ESeeder::AGENT_SLUG, 'Summarize today', $registry, 'php-sdk-reference');

    expect($run->isCompleted())->toBeTrue()
        ->and($run->response)->toBe('All berths are clear.')
        ->and($run->tokensIn)->toBeGreaterThan(0);

    // 6. Run status retrieval reflects the completed run.
    expect($this->client->getRun($run->runId)->isCompleted())->toBeTrue();

    // 7. MAAC recorded the run, its trace, usage/cost, and the implementation —
    //    everything the dashboard, SDK center, run trace, and audit log render.
    $record = AgentRun::firstWhere('slug', $run->runId);
    expect($record->caller)->toBe('php-sdk-reference')
        ->and($record->tokens_in)->toBeGreaterThan(0)
        ->and($record->cost)->toBeGreaterThan(0)
        ->and($record->traceEvents()->pluck('type')->all())->toContain(
            TraceEventType::RunRequested,
            TraceEventType::ToolRequired,
            TraceEventType::ToolResultReceived,
            TraceEventType::Resumed,
            TraceEventType::Completed,
        );

    expect(
        ToolImplementation::query()
            ->where('application_id', $this->application->id)
            ->where('status', 'implemented')
            ->exists(),
    )->toBeTrue();
});

it('reads run status through the SDK while a run is paused', function () {
    bindFakeRouter()->toolCallThen(MaacE2ESeeder::TOOL_SLUG, ['query' => 'today']);

    $paused = $this->client->startRun(MaacE2ESeeder::AGENT_SLUG, 'Summarize', 'status-check');

    expect($paused->isWaiting())->toBeTrue()
        ->and($paused->toolCall?->tool)->toBe(MaacE2ESeeder::TOOL_SLUG)
        ->and($paused->toolCall?->arguments)->toBe(['query' => 'today']);

    $fetched = $this->client->getRun($paused->runId);
    expect($fetched->isWaiting())->toBeTrue()
        ->and($fetched->runId)->toBe($paused->runId);
});
