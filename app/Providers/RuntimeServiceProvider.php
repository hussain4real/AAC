<?php

namespace App\Providers;

use App\Support\Runtime\AiLlmRouter;
use App\Support\Runtime\Contracts\LlmRouter;
use App\Support\Runtime\DeterministicLlmRouter;
use App\Support\Runtime\HostedTools\HostedToolRegistry;
use App\Support\Runtime\Knowledge\Contracts\KnowledgeRetriever;
use App\Support\Runtime\Knowledge\LexicalKnowledgeRetriever;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the agent runtime: binds the {@see LlmRouter} (the production
 * {@see AiLlmRouter} backed by the Laravel AI SDK, or the deterministic
 * {@see DeterministicLlmRouter} when `maac.runtime.driver` is `fake`), the
 * {@see KnowledgeRetriever} (the deterministic lexical retriever by default, so
 * an embedding-backed one can be swapped in without touching the executor), and
 * the hosted tool registry. Tests may also rebind the router with a scripted
 * fake so runs are reproducible without live provider calls.
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

        $this->app->bind(KnowledgeRetriever::class, LexicalKnowledgeRetriever::class);

        $this->app->singleton(HostedToolRegistry::class);
    }
}
