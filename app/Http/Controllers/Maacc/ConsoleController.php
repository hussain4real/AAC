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
use App\Models\VaultSecret;
use App\Support\MaaccConsolePageData;
use App\Support\Platform\PlatformAccessReport;
use App\Support\Sdk\VersionJourney;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;
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
    public function __construct(private readonly MaaccConsolePageData $pageData) {}

    public function applications(Request $request): Response
    {
        Gate::authorize('viewAny', Application::class);

        return Inertia::render('maacc/applications/index', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'applications'),
        ]);
    }

    public function application(Request $request): Response
    {
        $team = $request->user()->currentTeam()->firstOrFail();
        $application = $team->applications()->where('slug', (string) $request->route('application'))->firstOrFail();
        Gate::authorize('view', $application);

        return Inertia::render('maacc/applications/show', [
            'id' => $application->slug,
            'maacc' => fn (): array => $this->pageData->forPage($request, 'application', $application->slug),
        ]);
    }

    public function projects(Request $request): Response
    {
        Gate::authorize('viewAny', Project::class);

        return Inertia::render('maacc/projects/index', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'projects'),
        ]);
    }

    public function agents(Request $request): Response
    {
        Gate::authorize('viewAny', Agent::class);

        return Inertia::render('maacc/agents/index', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'agents'),
        ]);
    }

    public function createAgent(Request $request): Response
    {
        Gate::authorize('create', Agent::class);

        return Inertia::render('maacc/agents/create', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'agent-create'),
        ]);
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
            'maacc' => fn (): array => $this->pageData->forPage($request, 'agent', $slug),
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

    public function tools(Request $request): Response
    {
        Gate::authorize('viewAny', ToolContract::class);

        return Inertia::render('maacc/tools/index', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'tools'),
        ]);
    }

    public function tool(Request $request, VersionJourney $journey): Response
    {
        $slug = (string) $request->route('tool');
        $contract = $request->user()->currentTeam()->firstOrFail()
            ->toolContracts()->where('slug', $slug)->firstOrFail();
        Gate::authorize('view', $contract);

        return Inertia::render('maacc/tools/show', [
            'id' => $slug,
            'maacc' => fn (): array => $this->pageData->forPage($request, 'tool', $slug),
            // The tool's real lifecycle — contract version snapshots + SDK
            // implementation transitions — for the Audit history timeline.
            'history' => fn (): array => $journey->toolReport($contract),
        ]);
    }

    public function sdk(Request $request): Response
    {
        return Inertia::render('maacc/sdk', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'sdk'),
        ]);
    }

    public function sdkDocs(Request $request): Response
    {
        return Inertia::render('maacc/sdk-docs', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'sdk-docs'),
        ]);
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
            'maacc' => fn (): array => $this->pageData->forPage($request, 'sdk'),
        ]);
    }

    public function playground(Request $request): Response
    {
        return Inertia::render('maacc/playground', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'playground'),
        ]);
    }

    public function runs(Request $request): Response
    {
        Gate::authorize('viewAny', Agent::class);

        return Inertia::render('maacc/runs/index', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'runs'),
        ]);
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
            'maacc' => fn (): array => $this->pageData->forPage($request, 'run', $slug),
            // The run's real observability trace — the ordered lifecycle events
            // recorded by the runtime — for the Execution timeline.
            'trace' => fn (): array => TraceEventResource::collection($run->traceEvents()->orderBy('sequence')->get())->resolve(),
        ]);
    }

    public function llmProviders(Request $request): Response
    {
        return Inertia::render('maacc/llm-providers', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'llm-providers'),
        ]);
    }

    public function connectors(Request $request): Response
    {
        return Inertia::render('maacc/connectors', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'connectors'),
        ]);
    }

    public function knowledge(Request $request): Response
    {
        return Inertia::render('maacc/knowledge', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'knowledge'),
        ]);
    }

    public function dataSources(Request $request): Response
    {
        return Inertia::render('maacc/data-sources', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'data-sources'),
            // The approved, ops-provisioned read-only connection names a data
            // source may reference (config allowlist) — never MAACC's own DB.
            'connections' => array_values((array) config('maacc.runtime.db.allowed_connections', [])),
        ]);
    }

    public function evaluations(Request $request): Response
    {
        return Inertia::render('maacc/evaluations', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'evaluations'),
        ]);
    }

    public function governance(Request $request): Response
    {
        return Inertia::render('maacc/governance', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'governance'),
        ]);
    }

    public function webhooks(Request $request): Response
    {
        return Inertia::render('maacc/webhooks', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'webhooks'),
        ]);
    }

    public function vault(Request $request): Response
    {
        Gate::authorize('viewAny', VaultSecret::class);

        return Inertia::render('maacc/vault', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'vault'),
        ]);
    }

    public function routing(Request $request): Response
    {
        return Inertia::render('maacc/routing', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'routing'),
        ]);
    }

    public function identity(Request $request): Response
    {
        return Inertia::render('maacc/identity', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'identity'),
        ]);
    }

    public function incidents(Request $request): Response
    {
        return Inertia::render('maacc/incidents', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'incidents'),
        ]);
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
            'maacc' => fn (): array => $this->pageData->forPage($request, 'settings'),
            'access' => Inertia::defer(fn (): array => $report->forConsole($request), 'access-control', rescue: true),
            'directory' => Inertia::defer(fn (): array => $report->directoryPage($request), 'access-control', rescue: true),
            'capabilities' => fn (): array => $report->capabilities($request->user()),
        ]);
    }

    public function settings(Request $request): Response
    {
        $team = $request->user()->currentTeam()->firstOrFail();

        return Inertia::render('maacc/settings', [
            'maacc' => fn (): array => $this->pageData->forPage($request, 'settings'),
            'members' => Inertia::defer(function () use ($request, $team): array {
                $paginator = $team->memberships()
                    ->with('user')
                    ->orderBy('id')
                    ->cursorPaginate(
                        perPage: min(100, max(10, $request->integer('per_page', 25))),
                        cursorName: 'members_cursor',
                    );

                return [
                    'items' => collect($paginator->items())->map(fn (Membership $membership): array => [
                        'name' => $membership->user->name,
                        'email' => $membership->user->email,
                        'role' => $membership->role->label(),
                    ])->all(),
                    'pagination' => $this->pagination($paginator),
                ];
            }, 'members', rescue: true),
        ]);
    }

    /**
     * @template TKey of array-key
     * @template TValue
     *
     * @param  CursorPaginator<TKey, TValue>  $paginator
     * @return array<string, int|string|bool|null>
     */
    private function pagination(CursorPaginator $paginator): array
    {
        return [
            'count' => $paginator->count(),
            'perPage' => $paginator->perPage(),
            'hasMore' => $paginator->hasMorePages(),
            'nextCursor' => $paginator->nextCursor()?->encode(),
            'previousCursor' => $paginator->previousCursor()?->encode(),
        ];
    }
}
