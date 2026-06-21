<?php

namespace App\Providers;

use App\Support\Runtime\AiLlmRouter;
use App\Support\Runtime\Contracts\LlmRouter;
use App\Support\Runtime\DeterministicLlmRouter;
use App\Support\Runtime\HostedTools\HostedToolRegistry;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the agent runtime: binds the {@see LlmRouter} (the production
 * {@see AiLlmRouter} backed by the Laravel AI SDK, or the deterministic
 * {@see DeterministicLlmRouter} when `maac.runtime.driver` is `fake`) and the
 * hosted tool registry. Tests may also rebind the router with a scripted fake so
 * runs are reproducible without live provider calls.
 */
class RuntimeServiceProvider extends ServiceProvider
{
    /**
     * Register runtime services.
     */
    public function register(): void
    {
        $this->app->bind(LlmRouter::class, fn (Application $app): LlmRouter => $app->make(
            config('maac.runtime.driver') === 'fake'
                ? DeterministicLlmRouter::class
                : AiLlmRouter::class,
        ));

        $this->app->singleton(HostedToolRegistry::class);
    }
}
