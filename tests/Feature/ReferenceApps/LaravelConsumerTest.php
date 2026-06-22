<?php

use App\Actions\Maac\CreateCredential;
use App\Enums\RunStatus;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\User;
use Database\Seeders\MaacE2ESeeder;
use Illuminate\Support\Facades\Artisan;
use Maac\Reference\Laravel\Console\RunAgentCommand;
use Maac\Reference\Laravel\LaravelConsumer;
use Maac\Reference\Laravel\MaacServiceProvider;
use Maac\Sdk\Contracts\Transport;
use Tests\Support\Sdk\KernelTransport;

/**
 * Phase 6B: the Laravel reference consumer completes a real agent run — through
 * its own service provider, config, handler, and Artisan command — against a
 * seeded MAAC instance, using only the public SDK. The in-process kernel
 * transport is bound so the reference app's wiring is exercised end to end.
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

    config([
        'maac-consumer.base_url' => '',
        'maac-consumer.client_id' => $issued->credential->client_id,
        'maac-consumer.client_secret' => $issued->plainSecret,
        'maac-consumer.agent_slug' => MaacE2ESeeder::AGENT_SLUG,
        'maac-consumer.tools.fetch_records' => MaacE2ESeeder::TOOL_SLUG,
    ]);

    $this->app->bind(Transport::class, fn () => new KernelTransport($this));
    $this->app->register(MaacServiceProvider::class);
});

it('syncs implementations and completes a run through the resolved consumer', function () {
    bindFakeRouter()
        ->toolCallThen(MaacE2ESeeder::TOOL_SLUG, ['query' => 'today'])
        ->textThen('All vessels are on schedule.');

    $consumer = $this->app->make(LaravelConsumer::class);

    $sync = $consumer->syncImplementations();
    expect($sync[0]['status'])->toBe('implemented');

    $run = $consumer->summarize('Summarize today');

    expect($run->isCompleted())->toBeTrue()
        ->and($run->response)->toBe('All vessels are on schedule.');
});

it('runs the agent from the maac:run-agent artisan command', function () {
    // The provider's deferred command registration does not fire for a provider
    // registered after the console kernel booted, so register the real command
    // (resolving the provider-bound consumer) explicitly for the test.
    Artisan::registerCommand($this->app->make(RunAgentCommand::class));

    bindFakeRouter()
        ->toolCallThen(MaacE2ESeeder::TOOL_SLUG, ['query' => 'today'])
        ->textThen('Berths clear.');

    $this->artisan('maac:run-agent', ['prompt' => 'Summarize today'])
        ->assertSuccessful();

    expect(AgentRun::where('status', RunStatus::Completed)->where('caller', 'laravel-reference-cli')->exists())
        ->toBeTrue();
});
