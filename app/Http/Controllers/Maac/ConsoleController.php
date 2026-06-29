<?php

namespace App\Http\Controllers\Maac;

use App\Http\Controllers\Controller;
use App\Http\Resources\Maac\TraceEventResource;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\AgentVersion;
use App\Models\Membership;
use App\Support\Sdk\VersionJourney;
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
        $slug = (string) $request->route('agent');
        $team = $request->user()->currentTeam()->firstOrFail();
        $agent = Agent::query()
            ->whereHas('project.application', fn ($query) => $query->where('team_id', $team->id))
            ->where('slug', $slug)
            ->with(['versions' => fn ($query) => $query->with('publisher')->orderByDesc('created_at')])
            ->first();

        return Inertia::render('maac/agents/show', [
            'id' => $slug,
            // The agent's real published version history (from agent_versions),
            // newest first, for the Versions tab.
            'history' => fn (): ?array => $agent === null
                ? null
                : $agent->versions->map(fn (AgentVersion $version): array => [
                    'version' => $version->version,
                    'note' => $version->notes,
                    // A version is only attributed to a user once published; the
                    // initial draft (and any legacy row) has no publisher.
                    'author' => $version->published_by !== null ? $version->publisher->name : 'system',
                    'date' => ($version->published_at ?? $version->created_at)?->diffForHumans() ?? '—',
                    'current' => $version->version === $agent->version,
                ])->all(),
        ]);
    }

    public function tools(): Response
    {
        return Inertia::render('maac/tools/index');
    }

    public function tool(Request $request, VersionJourney $journey): Response
    {
        $slug = (string) $request->route('tool');
        $contract = $request->user()->currentTeam()->firstOrFail()
            ->toolContracts()->where('slug', $slug)->first();

        return Inertia::render('maac/tools/show', [
            'id' => $slug,
            // The tool's real lifecycle — contract version snapshots + SDK
            // implementation transitions — for the Audit history timeline.
            'history' => fn (): ?array => $contract === null ? null : $journey->toolReport($contract),
        ]);
    }

    public function sdk(): Response
    {
        return Inertia::render('maac/sdk');
    }

    public function sdkDocs(): Response
    {
        return Inertia::render('maac/sdk-docs');
    }

    /**
     * Render the tool version journey: the per-tool contract version history and
     * the per-application implementation timeline for the current team. The data
     * is a page-scoped lazy prop so it is only computed for this page.
     */
    public function journey(Request $request, VersionJourney $journey): Response
    {
        $team = $request->user()->currentTeam()->firstOrFail();

        return Inertia::render('maac/journey', [
            'journey' => fn (): array => $journey->teamReport($team),
        ]);
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
        $slug = (string) $request->route('run');
        $team = $request->user()->currentTeam()->firstOrFail();
        $run = AgentRun::query()
            ->whereHas('application', fn ($query) => $query->where('team_id', $team->id))
            ->where('slug', $slug)
            ->first();

        return Inertia::render('maac/runs/show', [
            'id' => $slug,
            // The run's real observability trace — the ordered lifecycle events
            // recorded by the runtime — for the Execution timeline.
            'trace' => fn (): ?array => $run === null
                ? null
                : TraceEventResource::collection($run->traceEvents()->orderBy('sequence')->get())->resolve(),
        ]);
    }

    public function llmProviders(): Response
    {
        return Inertia::render('maac/llm-providers');
    }

    public function connectors(): Response
    {
        return Inertia::render('maac/connectors');
    }

    public function knowledge(): Response
    {
        return Inertia::render('maac/knowledge');
    }

    public function dataSources(): Response
    {
        return Inertia::render('maac/data-sources', [
            // The approved, ops-provisioned read-only connection names a data
            // source may reference (config allowlist) — never MAAC's own DB.
            'connections' => array_values((array) config('maac.runtime.db.allowed_connections', [])),
        ]);
    }

    public function evaluations(): Response
    {
        return Inertia::render('maac/evaluations');
    }

    public function governance(): Response
    {
        return Inertia::render('maac/governance');
    }

    public function webhooks(): Response
    {
        return Inertia::render('maac/webhooks');
    }

    public function vault(): Response
    {
        return Inertia::render('maac/vault');
    }

    public function routing(): Response
    {
        return Inertia::render('maac/routing');
    }

    public function identity(): Response
    {
        return Inertia::render('maac/identity');
    }

    public function incidents(): Response
    {
        return Inertia::render('maac/incidents');
    }

    public function settings(Request $request): Response
    {
        $team = $request->user()->currentTeam()->firstOrFail();

        return Inertia::render('maac/settings', [
            // The team's real members and their team role for the Members tab.
            'members' => $team->memberships()
                ->with('user')
                ->get()
                ->map(fn (Membership $membership): array => [
                    'name' => $membership->user->name,
                    'email' => $membership->user->email,
                    'role' => $membership->role->label(),
                ])
                ->sortBy('name')
                ->values()
                ->all(),
        ]);
    }
}
