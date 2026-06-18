<?php

namespace App\Providers;

use App\Support\Runtime\AiLlmRouter;
use App\Support\Runtime\Contracts\LlmRouter;
use App\Support\Runtime\HostedTools\HostedToolRegistry;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the agent runtime: binds the production {@see LlmRouter} (backed by the
 * Laravel AI SDK) and the hosted tool registry. Tests rebind the router with a
 * deterministic fake so runs are reproducible without live provider calls.
 */
class RuntimeServiceProvider extends ServiceProvider
{
    /**
     * Register runtime services.
     */
    public function register(): void
    {
        $this->app->bind(LlmRouter::class, AiLlmRouter::class);
        $this->app->singleton(HostedToolRegistry::class);
    }
}
