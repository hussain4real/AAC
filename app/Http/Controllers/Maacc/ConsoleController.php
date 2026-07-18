<?php

namespace App\Http\Controllers\Maacc;

use App\Http\Controllers\Controller;
use App\Http\Resources\Maacc\TraceEventResource;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\AgentVersion;
use App\Models\Application;
use App\Models\Membership;
use App\Models\Project;
use App\Models\ToolContract;
use App\Support\Platform\PlatformAccessReport;
use App\Support\Sdk\VersionJourney;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MAACC console (Phase 1).
 *
 * These actions render the Inertia page shells for the management console.
 * Phase 1 is mock-backed on the client (resources/js/maacc/data.ts); detail
 * actions only forward the route identifier so the page can look the record
 * up in the fixture. Persistence and authorization arrive in Phase 2.
 */
class ConsoleController extends Controller
{
    public function applications(): Response
    {
        Gate::authorize('viewAny', Application::class);

        return Inertia::render('maacc/applications/index');
    }

    public function application(Request $request): Response
    {
        $team = $request->user()->currentTeam()->firstOrFail();
        $application = $team->applications()->where('slug', (string) $request->route('application'))->firstOrFail();
        Gate::authorize('view', $application);

        return Inertia::render('maacc/applications/show', ['id' => $application->slug]);
    }

    public function projects(): Response
    {
        Gate::authorize('viewAny', Project::class);

        return Inertia::render('maacc/projects/index');
    }

    public function agents(): Response
    {
        Gate::authorize('viewAny', Agent::class);

        return Inertia::render('maacc/agents/index');
    }

    public function createAgent(): Response
    {
        Gate::authorize('create', Agent::class);

        return Inertia::render('maacc/agents/create');
    }

    public function agent(Request $request): Response
    {
        $slug = (string) $request->route('agent');
        $team = $request->user()->currentTeam()->firstOrFail();
        $agent = Agent::query()
            ->whereHas('project.application', fn ($query) => $query->where('team_id', $team->id))
            ->where('slug', $slug)
            ->with(['versions' => fn ($query) => $query->with('publisher')->orderByDesc('created_at')])
            ->firstOrFail();
        Gate::authorize('view', $agent);

        return Inertia::render('maacc/agents/show', [
            'id' => $slug,
            // The agent's real published version history (from agent_versions),
            // newest first, for the Versions tab.
            'history' => fn (): array => $agent->versions->map(fn (AgentVersion $version): array => [
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
        Gate::authorize('viewAny', ToolContract::class);

        return Inertia::render('maacc/tools/index');
    }

    public function tool(Request $request, VersionJourney $journey): Response
    {
        $slug = (string) $request->route('tool');
        $contract = $request->user()->currentTeam()->firstOrFail()
            ->toolContracts()->where('slug', $slug)->firstOrFail();
        Gate::authorize('view', $contract);

        return Inertia::render('maacc/tools/show', [
            'id' => $slug,
            // The tool's real lifecycle — contract version snapshots + SDK
            // implementation transitions — for the Audit history timeline.
            'history' => fn (): array => $journey->toolReport($contract),
        ]);
    }

    public function sdk(): Response
    {
        return Inertia::render('maacc/sdk');
    }

    public function sdkDocs(): Response
    {
        return Inertia::render('maacc/sdk-docs');
    }

    /**
     * Render the tool version journey: the per-tool contract version history and
     * the per-application implementation timeline for the current team. The data
     * is a page-scoped lazy prop so it is only computed for this page.
     */
    public function journey(Request $request, VersionJourney $journey): Response
    {
        $team = $request->user()->currentTeam()->firstOrFail();

        return Inertia::render('maacc/journey', [
            'journey' => fn (): array => $journey->teamReport($team),
        ]);
    }

    public function playground(): Response
    {
        return Inertia::render('maacc/playground');
    }

    public function runs(): Response
    {
        Gate::authorize('viewAny', Agent::class);

        return Inertia::render('maacc/runs/index');
    }

    public function run(Request $request): Response
    {
        $slug = (string) $request->route('run');
        $team = $request->user()->currentTeam()->firstOrFail();
        $run = AgentRun::query()
            ->whereHas('application', fn ($query) => $query->where('team_id', $team->id))
            ->where('slug', $slug)
            ->firstOrFail();
        Gate::authorize('view', $run->agent);

        return Inertia::render('maacc/runs/show', [
            'id' => $slug,
            // The run's real observability trace — the ordered lifecycle events
            // recorded by the runtime — for the Execution timeline.
            'trace' => fn (): array => TraceEventResource::collection($run->traceEvents()->orderBy('sequence')->get())->resolve(),
        ]);
    }

    public function llmProviders(): Response
    {
        return Inertia::render('maacc/llm-providers');
    }

    public function connectors(): Response
    {
        return Inertia::render('maacc/connectors');
    }

    public function knowledge(): Response
    {
        return Inertia::render('maacc/knowledge');
    }

    public function dataSources(): Response
    {
        return Inertia::render('maacc/data-sources', [
            // The approved, ops-provisioned read-only connection names a data
            // source may reference (config allowlist) — never MAACC's own DB.
            'connections' => array_values((array) config('maacc.runtime.db.allowed_connections', [])),
        ]);
    }

    public function evaluations(): Response
    {
        return Inertia::render('maacc/evaluations');
    }

    public function governance(): Response
    {
        return Inertia::render('maacc/governance');
    }

    public function webhooks(): Response
    {
        return Inertia::render('maacc/webhooks');
    }

    public function vault(): Response
    {
        return Inertia::render('maacc/vault');
    }

    public function routing(): Response
    {
        return Inertia::render('maacc/routing');
    }

    public function identity(): Response
    {
        return Inertia::render('maacc/identity');
    }

    public function incidents(): Response
    {
        return Inertia::render('maacc/incidents');
    }

    /**
     * Render the MAACC platform-administration Access Control page (Phase 8B):
     * the platform role/permission catalogue, every platform admin with their
     * grants, the access-review work lists, and the audit trail. The route is
     * gated by the platform `users.view` permission; write actions live on
     * {@see PlatformAccessController}.
     */
    public function accessControl(Request $request, PlatformAccessReport $report): Response
    {
        return Inertia::render('maacc/access-control', [
            'access' => fn (): array => $report->forConsole(),
            'directory' => fn (): array => $report->directory(),
            'capabilities' => fn (): array => $report->capabilities($request->user()),
        ]);
    }

    public function settings(Request $request): Response
    {
        $team = $request->user()->currentTeam()->firstOrFail();

        return Inertia::render('maacc/settings', [
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
