<?php

namespace App\Providers;

use App\Support\Runtime\AiLlmRouter;
use App\Support\Runtime\Contracts\LlmRouter;
use App\Support\Runtime\DeterministicLlmRouter;
use App\Support\Runtime\HostedTools\HostedToolRegistry;
use App\Support\Runtime\HostedTools\ProviderHostedToolRegistry;
use App\Support\Runtime\Knowledge\Contracts\KnowledgeRetriever;
use App\Support\Runtime\Knowledge\LexicalKnowledgeRetriever;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the agent runtime: binds the {@see LlmRouter} (the production
 * {@see AiLlmRouter} backed by the Laravel AI SDK, or the deterministic
 * {@see DeterministicLlmRouter} when `maacc.runtime.driver` is `fake`), the
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
        $this->app->bind(LlmRouter::class, fn (Application $app): LlmRouter => $app->make($this->routerClass($app)));

        $this->app->bind(KnowledgeRetriever::class, LexicalKnowledgeRetriever::class);

        $this->app->singleton(HostedToolRegistry::class);
        $this->app->singleton(ProviderHostedToolRegistry::class);
    }

    /**
     * Resolve which {@see LlmRouter} implementation to bind.
     *
     * The deterministic router is a validation/CI aid, never a production
     * component. If a stray `MAACC_LLM_DRIVER=fake` reaches production — e.g.
     * a demo/validation `.env` that was never flipped back — we self-heal to
     * the live {@see AiLlmRouter} and log a warning, so a misconfigured
     * environment can never silently replace real model calls with canned
     * responses. Outside production the flag is honoured so the validation
     * harness and local smoke runs stay dependency-free.
     *
     * @return class-string<LlmRouter>
     */
    private function routerClass(Application $app): string
    {
        if (config('maacc.runtime.driver') !== 'fake') {
            return AiLlmRouter::class;
        }

        if ($app->isProduction()) {
            Log::warning('MAACC_LLM_DRIVER=fake was ignored in production; binding the live AiLlmRouter. Set MAACC_LLM_DRIVER=ai (or remove it) in the production .env to silence this warning.');

            return AiLlmRouter::class;
        }

        return DeterministicLlmRouter::class;
    }
}
