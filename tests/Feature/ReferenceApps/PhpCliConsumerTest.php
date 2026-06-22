<?php

use App\Actions\Maac\CreateCredential;
use App\Models\Application;
use App\Models\User;
use Database\Seeders\MaacE2ESeeder;
use Illuminate\Support\Facades\Artisan;
use Maac\Reference\Cli\CliConsumer;
use Maac\Reference\Cli\FetchRecordsHandler;
use Maac\Sdk\MaacClient;
use Maac\Sdk\MaacConfig;
use Maac\Sdk\Tools\ToolHandlerRegistry;
use Tests\Support\Sdk\KernelTransport;

/**
 * Phase 6B: the plain-PHP CLI reference consumer (no Laravel, no container)
 * completes a real agent run against a seeded MAAC instance, proving the
 * integration contract is reusable from a bare PHP runtime via the shared SDK.
 */
beforeEach(function () {
    if (! file_exists(storage_path('oauth-private.key'))) {
        Artisan::call('passport:keys');
    }

    $this->seed(MaacE2ESeeder::class);
    $application = Application::firstWhere('slug', MaacE2ESeeder::APP_SLUG);

    $issued = app(CreateCredential::class)->handle(
        $application,
        User::firstWhere('email', MaacE2ESeeder::USER_EMAIL),
        ['environment' => 'production'],
    );

    $client = new MaacClient(
        new MaacConfig('', $issued->credential->client_id, $issued->plainSecret),
        new KernelTransport($this),
    );

    $registry = (new ToolHandlerRegistry)->register(new FetchRecordsHandler(MaacE2ESeeder::TOOL_SLUG));
    $this->consumer = new CliConsumer($client, $registry, MaacE2ESeeder::AGENT_SLUG);
});

it('syncs its plain-php handler as implemented', function () {
    $results = $this->consumer->syncImplementations();

    expect($results[0]['tool'])->toBe(MaacE2ESeeder::TOOL_SLUG)
        ->and($results[0]['status'])->toBe('implemented');
});

it('completes an agent run with a client-side tool from plain php', function () {
    bindFakeRouter()
        ->toolCallThen(MaacE2ESeeder::TOOL_SLUG, ['query' => 'berth'])
        ->textThen('Operations nominal.');

    $run = $this->consumer->run('Summarize current operations');

    expect($run->isCompleted())->toBeTrue()
        ->and($run->response)->toBe('Operations nominal.')
        ->and($run->cost)->toBeGreaterThan(0);
});
