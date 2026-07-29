<?php

namespace App\Support;

use App\Http\Resources\Maacc\AgentResource;
use App\Http\Resources\Maacc\AgentRunResource;
use App\Http\Resources\Maacc\ApplicationResource;
use App\Http\Resources\Maacc\DataSourceResource;
use App\Http\Resources\Maacc\EvaluationDatasetResource;
use App\Http\Resources\Maacc\EvaluationResource;
use App\Http\Resources\Maacc\KnowledgeSourceResource;
use App\Http\Resources\Maacc\LlmProviderResource;
use App\Http\Resources\Maacc\McpConnectorResource;
use App\Http\Resources\Maacc\ProjectResource;
use App\Http\Resources\Maacc\ToolContractResource;
use App\Http\Resources\Maacc\WebhookEndpointResource;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\DataSource;
use App\Models\Evaluation;
use App\Models\EvaluationDataset;
use App\Models\KnowledgeSource;
use App\Models\McpConnector;
use App\Models\Project;
use App\Models\Team;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\Observability\OperationalMonitor;
use App\Support\Observability\RunMetrics;
use App\Support\Sdk\SdkCompatibilityReport;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Assembles the MAACC console dataset for a team as plain arrays matching the
 * Phase 1 fixture (resources/js/maacc/data.ts). Shared with every console page
 * so the client-side scope/persona layer can filter real records instead of
 * the static mock data.
 */
class MaaccConsoleData
{
    /**
     * Cache key holding the monotonic version stamp for the console dataset.
     * Bumped by {@see self::invalidate()} on any console-scoped write so cached
     * payloads are abandoned (a new key) rather than served stale.
     */
    private const VERSION_KEY = 'maacc:console:version';

    /**
     * Backstop TTL (seconds). Writes bump the version immediately, so this only
     * bounds worst-case staleness if an invalidation is ever missed.
     */
    private const CACHE_TTL = 300;

    /**
     * Return the console dataset for a team, served from cache and rebuilt only
     * when a write has bumped the version. This runs on every authenticated
     * request (shared Inertia prop), so caching it keeps navigation fast.
     *
     * @return array<string, mixed>
     */
    public static function forTeam(Team $team): array
    {
        $version = (int) Cache::get(self::VERSION_KEY, 1);

        return Cache::remember(
            "maacc:console:v{$version}:team:{$team->getKey()}",
            self::CACHE_TTL,
            static fn (): array => self::build($team),
        );
    }

    /**
     * Return only the records the actor is authorized to receive. Platform
     * administrators retain the complete team dataset; project roles receive a
     * fail-closed projection of their active project memberships.
     *
     * @return array<string, mixed>
     */
    public static function forUser(User $user, Team $team): array
    {
        $data = self::forTeam($team);
        $access = app(MaaccAccess::class)->forUser($user, $team);

        if ($access['isPlatformAdmin']) {
            return $data;
        }

        $projectIds = collect($access['projectIds']);
        $projects = self::rows($data['projects'] ?? null)->filter(
            fn (array $project): bool => $projectIds->contains($project['uuid'] ?? null),
        )->values();
        $projectSlugs = $projects->pluck('id');
        $applicationSlugs = $projects->pluck('appId')->unique();
        $agents = self::rows($data['agents'] ?? null)->filter(
            fn (array $agent): bool => $projectSlugs->contains($agent['projectId'] ?? null),
        )->values();
        $agentSlugs = $agents->pluck('id');
        $tools = self::rows($data['tools'] ?? null)->filter(function (array $tool) use ($agentSlugs, $applicationSlugs): bool {
            return $applicationSlugs->contains($tool['appId'] ?? null)
                || collect(is_array($tool['usedBy'] ?? null) ? $tool['usedBy'] : [])->intersect($agentSlugs)->isNotEmpty();
        })->values();
        $runs = self::rows($data['runs'] ?? null)->filter(
            fn (array $run): bool => $projectSlugs->contains($run['projectId'] ?? null),
        )->values();
        $llmSlugs = $projects->flatMap(fn (array $project): array => $project['llms'] ?? [])->unique();
        $memberDirectory = [];

        if (in_array('project:manage', $access['permissions'], true)) {
            $memberDirectory = $data['memberDirectory'];
        }

        return [
            ...$data,
            'apps' => self::rows($data['apps'] ?? null)->filter(fn (array $application): bool => $applicationSlugs->contains($application['id'] ?? null))->values()->all(),
            'projects' => $projects->all(),
            'agents' => $agents->all(),
            'tools' => $tools->all(),
            'runs' => $runs->all(),
            'llms' => self::rows($data['llms'] ?? null)->filter(fn (array $llm): bool => $llmSlugs->contains($llm['id'] ?? null))->values()->all(),
            'dashboard' => self::scopedDashboard($data['dashboard'], $projects->count(), $agents, $tools, $runs),
            'operational' => self::scopedOperational($runs),
            'sdkCompatibility' => [
                ...$data['sdkCompatibility'],
                'applications' => self::rows(is_array($data['sdkCompatibility'] ?? null) ? ($data['sdkCompatibility']['applications'] ?? null) : null)->filter(fn (array $application): bool => $applicationSlugs->contains($application['id'] ?? null))->values()->all(),
                'drift' => self::rows(is_array($data['sdkCompatibility'] ?? null) ? ($data['sdkCompatibility']['drift'] ?? null) : null)->filter(fn (array $drift): bool => $applicationSlugs->contains($drift['applicationId'] ?? null))->values()->all(),
            ],
            'webhooks' => self::rows($data['webhooks'] ?? null)->filter(fn (array $webhook): bool => $applicationSlugs->contains($webhook['appId'] ?? null))->values()->all(),
            'evaluationDatasets' => self::rows($data['evaluationDatasets'] ?? null)->filter(fn (array $dataset): bool => $projectIds->contains($dataset['projectId'] ?? null))->values()->all(),
            'evaluations' => self::rows($data['evaluations'] ?? null)->filter(fn (array $evaluation): bool => $agentSlugs->contains($evaluation['agentSlug'] ?? null))->values()->all(),
            // These team-wide control planes need dedicated object scopes. Until
            // then project actors receive no records rather than an over-broad
            // team corpus.
            'connectors' => [],
            'knowledgeSources' => [],
            'dataSources' => [],
            'approvals' => ['tools' => [], 'agents' => [], 'models' => [], 'data' => [], 'runtime' => []],
            'auditEvents' => [],
            'roles' => [],
            'quotas' => [],
            'vaultSecrets' => [],
            'routingPolicies' => [],
            'providerHealth' => [],
            'incidents' => [],
            'ssoConnections' => [],
            'memberDirectory' => $memberDirectory,
        ];
    }

    /**
     * Normalize an untrusted console-data field into typed object rows.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private static function rows(mixed $value): Collection
    {
        if (! is_array($value)) {
            return collect();
        }

        $rows = [];

        foreach ($value as $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            $row = [];

            foreach ($candidate as $key => $item) {
                if (is_string($key)) {
                    $row[$key] = $item;
                }
            }

            $rows[] = $row;
        }

        return collect($rows);
    }

    /**
     * Bump the console cache version so every team's dataset is rebuilt on the
     * next read. Invoked on any write to a console-scoped model, from web or the
     * queue worker (they share the cache store), keeping the cache coherent.
     */
    public static function invalidate(): void
    {
        Cache::add(self::VERSION_KEY, 1);
        Cache::increment(self::VERSION_KEY);
    }

    /**
     * Build the full console dataset for the given team.
     *
     * @return array<string, mixed>
     */
    private static function build(Team $team): array
    {
        $applications = $team->applications()
            ->with('credentials')
            ->orderBy('name')
            ->get();

        $projects = Project::query()
            ->whereHas('application', fn ($query) => $query->where('team_id', $team->id))
            ->with(['application', 'llmProviders', 'projectMembers.user', 'projectMembers.grantor', 'projectMembers.revoker', 'projectMembers.certifier'])
            ->orderBy('name')
            ->get();

        $agents = Agent::query()
            ->whereHas('project.application', fn ($query) => $query->where('team_id', $team->id))
            ->with(['project.application', 'llmProvider', 'tools'])
            ->orderBy('name')
            ->get();

        $tools = $team->toolContracts()
            ->with(['application', 'agents', 'implementations', 'mcpConnector', 'knowledgeSource', 'dataSource'])
            ->orderBy('name')
            ->get();

        $connectors = McpConnector::query()
            ->where('team_id', $team->id)
            ->with('application')
            ->withCount('tools')
            ->orderBy('name')
            ->get();

        $knowledgeSources = KnowledgeSource::query()
            ->where('team_id', $team->id)
            ->with(['application', 'documents' => fn ($query) => $query->withCount('chunks')->latest()])
            ->withCount('tools')
            ->orderBy('name')
            ->get();

        $dataSources = DataSource::query()
            ->where('team_id', $team->id)
            ->with('application')
            ->withCount('tools')
            ->orderBy('name')
            ->get();

        $evaluationDatasets = EvaluationDataset::query()
            ->where('team_id', $team->id)
            ->with(['project', 'cases'])
            ->withCount('cases')
            ->orderBy('name')
            ->get();

        $evaluations = Evaluation::query()
            ->where('team_id', $team->id)
            ->with(['agent', 'dataset', 'results' => fn ($query) => $query->with('run')->orderBy('created_at')])
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();

        $runs = AgentRun::query()
            ->whereHas('application', fn ($query) => $query->where('team_id', $team->id))
            ->with(['agent', 'application', 'project', 'llmProvider'])
            ->orderByDesc('started_at')
            ->get();

        $llms = $team->llmProviders()
            ->with('vaultSecret')
            ->orderByDesc('usage_pct')
            ->get();

        $webhooks = WebhookEndpoint::query()
            ->whereHas('application', fn ($query) => $query->where('team_id', $team->id))
            ->with(['application', 'deliveries' => fn ($query) => $query->with('agentRun')->latest()->limit(15)])
            ->orderByDesc('created_at')
            ->get();

        $operational = app(OperationalMonitor::class)->forTeam($team);

        return [
            'apps' => ApplicationResource::collection($applications)->resolve(),
            'projects' => ProjectResource::collection($projects)->resolve(),
            'agents' => AgentResource::collection($agents)->resolve(),
            'tools' => ToolContractResource::collection($tools)->resolve(),
            'runs' => AgentRunResource::collection($runs)->resolve(),
            'llms' => LlmProviderResource::collection($llms)->resolve(),
            'providerCatalog' => ProviderCatalog::providers(),
            'memberDirectory' => $team->members()->orderBy('name')->get()->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->all(),
            // Phase 5 — real observability rollups and governance dataset.
            'dashboard' => [
                ...app(RunMetrics::class)->forTeam($team),
                'alerts' => $operational['alerts'],
            ],
            'operational' => $operational['metrics'],
            // Phase 6C — SDK versioning/compatibility dashboard dataset.
            'sdkCompatibility' => app(SdkCompatibilityReport::class)->forTeam($team),
            // Phase 6D — webhook endpoints + recent delivery history.
            'webhooks' => WebhookEndpointResource::collection($webhooks)->resolve(),
            // Phase 6E — registered MCP connectors + discovered capabilities.
            'connectors' => McpConnectorResource::collection($connectors)->resolve(),
            // Phase 6F — knowledge (RAG) sources and the evaluation lab.
            'knowledgeSources' => KnowledgeSourceResource::collection($knowledgeSources)->resolve(),
            // Phase 8A — governed read-only data sources for db tools.
            'dataSources' => DataSourceResource::collection($dataSources)->resolve(),
            'evaluationDatasets' => EvaluationDatasetResource::collection($evaluationDatasets)->resolve(),
            'evaluations' => EvaluationResource::collection($evaluations)->resolve(),
            ...GovernanceConsoleData::forTeam($team),
            // Phase 6G — enterprise identity, secrets vault & advanced governance.
            ...EnterpriseConsoleData::forTeam($team),
        ];
    }

    /**
     * @param  array<string, mixed>  $dashboard
     * @param  Collection<int, array<string, mixed>>  $agents
     * @param  Collection<int, array<string, mixed>>  $tools
     * @param  Collection<int, array<string, mixed>>  $runs
     * @return array<string, mixed>
     */
    private static function scopedDashboard(array $dashboard, int $projectCount, Collection $agents, Collection $tools, Collection $runs): array
    {
        $today = now()->format('d M');

        return [
            ...$dashboard,
            'stats' => [
                ...$dashboard['stats'],
                'apps' => $agents->pluck('appId')->unique()->count(),
                'projects' => $projectCount,
                'agents' => $agents->count(),
                'tools' => $tools->count(),
                'runsToday' => $runs->filter(fn (array $run): bool => str_starts_with((string) ($run['started'] ?? ''), $today))->count(),
                'waitingClient' => $runs->where('status', 'waiting_for_client')->count(),
                'success' => $runs->where('status', 'completed')->count(),
                'failed' => $runs->where('status', 'failed')->count(),
            ],
            'topAgents' => self::rows($dashboard['topAgents'] ?? null)->filter(fn (array $agent): bool => $agents->contains('id', $agent['id'] ?? null))->values()->all(),
            'alerts' => [],
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $runs
     * @return array<string, int|float|bool>
     */
    private static function scopedOperational(Collection $runs): array
    {
        $failed = $runs->where('status', 'failed')->count();
        $total = $runs->count();

        return [
            'totalRuns' => $total,
            'failedRuns' => $failed,
            'expiredRuns' => $runs->where('status', 'expired')->count(),
            'waitingRuns' => $runs->where('status', 'waiting_for_client')->count(),
            'avgLatencyMs' => $total === 0 ? 0 : (int) round($runs->avg('latencyMs')),
            'errorRate' => $total === 0 ? 0 : round(($failed / $total) * 100, 2),
            'toolFailureRate' => 0,
            'costAnomaly' => false,
        ];
    }
}
