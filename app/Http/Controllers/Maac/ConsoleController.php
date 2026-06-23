<?php

namespace App\Http\Controllers\Maac;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MAAC console (Phase 1).
 *
 * These actions render the Inertia page shells for the management console.
 * Phase 1 is mock-backed on the client (resources/js/maac/data.ts); detail
 * actions only forward the route identifier so the page can look the record
 * up in the fixture. Persistence and authorization arrive in Phase 2.
 */
class ConsoleController extends Controller
{
    public function applications(): Response
    {
        return Inertia::render('maac/applications/index');
    }

    public function application(Request $request): Response
    {
        return Inertia::render('maac/applications/show', ['id' => $request->route('application')]);
    }

    public function projects(): Response
    {
        return Inertia::render('maac/projects/index');
    }

    public function agents(): Response
    {
        return Inertia::render('maac/agents/index');
    }

    public function createAgent(): Response
    {
        return Inertia::render('maac/agents/create');
    }

    public function agent(Request $request): Response
    {
        return Inertia::render('maac/agents/show', ['id' => $request->route('agent')]);
    }

    public function tools(): Response
    {
        return Inertia::render('maac/tools/index');
    }

    public function tool(Request $request): Response
    {
        return Inertia::render('maac/tools/show', ['id' => $request->route('tool')]);
    }

    public function sdk(): Response
    {
        return Inertia::render('maac/sdk');
    }

    public function sdkDocs(): Response
    {
        return Inertia::render('maac/sdk-docs');
    }

    public function playground(): Response
    {
        return Inertia::render('maac/playground');
    }

    public function runs(): Response
    {
        return Inertia::render('maac/runs/index');
    }

    public function run(Request $request): Response
    {
        return Inertia::render('maac/runs/show', ['id' => $request->route('run')]);
    }

    public function llmProviders(): Response
    {
        return Inertia::render('maac/llm-providers');
    }

    public function connectors(): Response
    {
        return Inertia::render('maac/connectors');
    }

    public function governance(): Response
    {
        return Inertia::render('maac/governance');
    }

    public function webhooks(): Response
    {
        return Inertia::render('maac/webhooks');
    }

    public function settings(): Response
    {
        return Inertia::render('maac/settings');
    }
}
