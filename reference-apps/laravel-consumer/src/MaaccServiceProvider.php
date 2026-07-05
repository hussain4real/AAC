<?php

declare(strict_types=1);

namespace Maacc\Reference\Laravel;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Maacc\Reference\Laravel\Console\RunAgentCommand;
use Maacc\Reference\Laravel\Handlers\FetchRecordsHandler;
use Maacc\Reference\Laravel\Support\CargoRepository;
use Maacc\Sdk\Contracts\Transport;
use Maacc\Sdk\MaaccClient;
use Maacc\Sdk\MaaccConfig;
use Maacc\Sdk\Tools\ToolHandlerRegistry;

/**
 * Idiomatic Laravel wiring for the MAACC integration: it binds a configured
 * {@see LaravelConsumer} (SDK client + local handler registry) as a singleton
 * and registers the demo Artisan command. A bound {@see Transport} is honoured
 * when present — production leaves it unbound (defaulting to cURL), while tests
 * bind an in-process transport.
 */
final class MaaccServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/maacc-consumer.php', 'maacc-consumer');

        $this->app->singleton(LaravelConsumer::class, function (Application $app): LaravelConsumer {
            /** @var array<string, mixed> $config */
            $config = (array) config('maacc-consumer');

            $maaccConfig = new MaaccConfig(
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
                new MaaccClient($maaccConfig, $transport),
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
