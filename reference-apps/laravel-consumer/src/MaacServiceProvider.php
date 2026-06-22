<?php

declare(strict_types=1);

namespace Maac\Reference\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Maac\Reference\Laravel\Console\RunAgentCommand;
use Maac\Reference\Laravel\Handlers\FetchRecordsHandler;
use Maac\Reference\Laravel\Support\CargoRepository;
use Maac\Sdk\Contracts\Transport;
use Maac\Sdk\MaacClient;
use Maac\Sdk\MaacConfig;
use Maac\Sdk\Tools\ToolHandlerRegistry;

/**
 * Idiomatic Laravel wiring for the MAAC integration: it binds a configured
 * {@see LaravelConsumer} (SDK client + local handler registry) as a singleton
 * and registers the demo Artisan command. A bound {@see Transport} is honoured
 * when present — production leaves it unbound (defaulting to cURL), while tests
 * bind an in-process transport.
 */
final class MaacServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/maac-consumer.php', 'maac-consumer');

        $this->app->singleton(LaravelConsumer::class, function (Application $app): LaravelConsumer {
            /** @var array<string, mixed> $config */
            $config = (array) config('maac-consumer');

            $maacConfig = new MaacConfig(
                baseUrl: (string) ($config['base_url'] ?? ''),
                clientId: (string) ($config['client_id'] ?? ''),
                clientSecret: (string) ($config['client_secret'] ?? ''),
            );

            $transport = $app->bound(Transport::class) ? $app->make(Transport::class) : null;

            $tools = is_array($config['tools'] ?? null) ? $config['tools'] : [];
            $registry = (new ToolHandlerRegistry)->register(
                new FetchRecordsHandler(new CargoRepository, (string) ($tools['fetch_records'] ?? '')),
            );

            return new LaravelConsumer(
                new MaacClient($maacConfig, $transport),
                $registry,
                (string) ($config['agent_slug'] ?? ''),
            );
        });
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([RunAgentCommand::class]);
        }
    }
}
