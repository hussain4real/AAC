<?php

use App\Actions\Maacc\CreateCredential;
use App\Enums\RunStatus;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\User;
use Database\Seeders\MaaccE2ESeeder;
use Illuminate\Support\Facades\Artisan;
use Maacc\Reference\Laravel\Console\RunAgentCommand;
use Maacc\Reference\Laravel\LaravelConsumer;
use Maacc\Reference\Laravel\MaaccServiceProvider;
use Maacc\Sdk\Contracts\Transport;
use Tests\Support\Sdk\KernelTransport;

/**
 * Phase 6B: the Laravel reference consumer completes a real agent run — through
 * its own service provider, config, handler, and Artisan command — against a
 * seeded MAACC instance, using only the public SDK. The in-process kernel
 * transport is bound so the reference app's wiring is exercised end to end.
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

    config([
        'maacc-consumer.base_url' => '',
        'maacc-consumer.client_id' => $issued->credential->client_id,
        'maacc-consumer.client_secret' => $issued->plainSecret,
        'maacc-consumer.agent_slug' => MaaccE2ESeeder::AGENT_SLUG,
        'maacc-consumer.tools.fetch_records' => MaaccE2ESeeder::TOOL_SLUG,
    ]);

    $this->app->bind(Transport::class, fn () => new KernelTransport($this));
    $this->app->register(MaaccServiceProvider::class);
});

it('syncs implementations and completes a run through the resolved consumer', function () {
    bindFakeRouter()
        ->toolCallThen(MaaccE2ESeeder::TOOL_SLUG, ['query' => 'today'])
        ->textThen('All vessels are on schedule.');

    $consumer = $this->app->make(LaravelConsumer::class);

    $sync = $consumer->syncImplementations();
    expect($sync[0]['status'])->toBe('implemented');

    $run = $consumer->summarize('Summarize today');

    expect($run->isCompleted())->toBeTrue()
        ->and($run->response)->toBe('All vessels are on schedule.');
});

it('runs the agent from the maacc:run-agent artisan command', function () {
    // The provider's deferred command registration does not fire for a provider
    // registered after the console kernel booted, so register the real command
    // (resolving the provider-bound consumer) explicitly for the test.
    Artisan::registerCommand($this->app->make(RunAgentCommand::class));

    bindFakeRouter()
        ->toolCallThen(MaaccE2ESeeder::TOOL_SLUG, ['query' => 'today'])
        ->textThen('Berths clear.');

    $this->artisan('maacc:run-agent', ['prompt' => 'Summarize today'])
        ->assertSuccessful();

    expect(AgentRun::where('status', RunStatus::Completed)->where('caller', 'laravel-reference-cli')->exists())
        ->toBeTrue();
});
