<?php

use App\Actions\Maacc\CreateCredential;
use App\Models\Application;
use App\Models\User;
use Database\Seeders\MaaccE2ESeeder;
use Illuminate\Support\Facades\Artisan;
use Maacc\Reference\Cli\CliConsumer;
use Maacc\Reference\Cli\FetchRecordsHandler;
use Maacc\Sdk\MaaccClient;
use Maacc\Sdk\MaaccConfig;
use Maacc\Sdk\Tools\ToolHandlerRegistry;
use Tests\Support\Sdk\KernelTransport;

/**
 * Phase 6B: the plain-PHP CLI reference consumer (no Laravel, no container)
 * completes a real agent run against a seeded MAACC instance, proving the
 * integration contract is reusable from a bare PHP runtime via the shared SDK.
 */
beforeEach(function () {
    if (! file_exists(storage_path('oauth-private.key'))) {
        Artisan::call('passport:keys');
    }

    $this->seed(MaaccE2ESeeder::class);
    $application = Application::firstWhere('slug', MaaccE2ESeeder::APP_SLUG);

    $issued = app(CreateCredential::class)->handle(
        $application,
        User::firstWhere('email', MaaccE2ESeeder::USER_EMAIL),
        ['environment' => 'production'],
    );

    $client = new MaaccClient(
        new MaaccConfig('', $issued->credential->client_id, $issued->plainSecret),
        new KernelTransport($this),
    );

    $registry = (new ToolHandlerRegistry)->register(new FetchRecordsHandler(MaaccE2ESeeder::TOOL_SLUG));
    $this->consumer = new CliConsumer($client, $registry, MaaccE2ESeeder::AGENT_SLUG);
});

it('syncs its plain-php handler as implemented', function () {
    $results = $this->consumer->syncImplementations();

    expect($results[0]['tool'])->toBe(MaaccE2ESeeder::TOOL_SLUG)
        ->and($results[0]['status'])->toBe('implemented');
});

it('completes an agent run with a client-side tool from plain php', function () {
    bindFakeRouter()
        ->toolCallThen(MaaccE2ESeeder::TOOL_SLUG, ['query' => 'berth'])
        ->textThen('Operations nominal.');

    $run = $this->consumer->run('Summarize current operations');

    expect($run->isCompleted())->toBeTrue()
        ->and($run->response)->toBe('Operations nominal.')
        ->and($run->cost)->toBeGreaterThan(0);
});
