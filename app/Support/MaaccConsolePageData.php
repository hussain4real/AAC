<?php

namespace App\Support;

use App\Http\Resources\Maacc\AgentResource;
use App\Http\Resources\Maacc\AgentRunResource;
use App\Http\Resources\Maacc\ApplicationResource;
use App\Http\Resources\Maacc\ApprovalRequestResource;
use App\Http\Resources\Maacc\AuditEventResource;
use App\Http\Resources\Maacc\DataSourceResource;
use App\Http\Resources\Maacc\EvaluationDatasetResource;
use App\Http\Resources\Maacc\EvaluationResource;
use App\Http\Resources\Maacc\IncidentActionResource;
use App\Http\Resources\Maacc\KnowledgeSourceResource;
use App\Http\Resources\Maacc\LlmProviderResource;
use App\Http\Resources\Maacc\McpConnectorResource;
use App\Http\Resources\Maacc\ModelRoutingPolicyResource;
use App\Http\Resources\Maacc\ProjectResource;
use App\Http\Resources\Maacc\SsoConnectionResource;
use App\Http\Resources\Maacc\ToolContractResource;
use App\Http\Resources\Maacc\VaultSecretResource;
use App\Http\Resources\Maacc\WebhookEndpointResource;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\ApprovalRequest;
use App\Models\AuditEvent;
use App\Models\DataSource;
use App\Models\Evaluation;
use App\Models\EvaluationDataset;
use App\Models\IncidentAction;
use App\Models\KnowledgeSource;
use App\Models\McpConnector;
use App\Models\Project;
use App\Models\SsoConnection;
use App\Models\Team;
use App\Models\ToolContract;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\Observability\OperationalMonitor;
use App\Support\Observability\RunMetrics;
use App\Support\Runtime\Routing\ProviderHealth;
use App\Support\Sdk\SdkCompatibilityReport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Pagination\CursorPaginator;

/**
 * Builds the bounded, authorized data contract for one MAACC console page.
 *
 * The previous console contract serialized the complete team corpus on every
 * authenticated Inertia response. This service keeps page payloads explicit,
 * applies project access in SQL, and cursor-paginates operational collections.
 */
class MaaccConsolePageData
{
    private const DEFAULT_PAGE_SIZE = 25;

    private const MAX_PAGE_SIZE = 100;

    /**
     * @return array<string, mixed>
     */
    public function forPage(Request $request, string $page, ?string $resourceSlug = null): array
    {
        $user = $request->user();
        $team = $user?->currentTeam()->first();

        if (! $user instanceof User || ! $team instanceof Team) {
            return $this->emptyContract();
        }

        $projectIds = $this->authorizedProjectIds($user, $team);
        $data = match ($page) {
            'dashboard' => $this->dashboard($team, $projectIds),
            'applications' => ['apps' => $this->applications($team, $projectIds)],
            'application' => $this->application($team, $projectIds, $resourceSlug),
            'projects' => $this->projectsPage($team, $projectIds),
            'agents' => $this->agentsPage($team, $projectIds),
            'agent-create' => $this->agentCreatePage($team, $projectIds),
            'agent' => $this->agentPage($team, $projectIds, $resourceSlug),
            'tools' => $this->toolsPage($request, $team, $projectIds),
            'tool' => $this->toolPage($team, $projectIds, $resourceSlug),
            'sdk', 'sdk-docs' => $this->sdkPage($team, $projectIds),
            'playground' => $this->playgroundPage($team, $projectIds),
            'runs' => $this->runsPage($request, $team, $projectIds),
            'run' => $this->runPage($team, $projectIds, $resourceSlug),
            'llm-providers' => $this->llmPage($team),
            'connectors' => $this->connectorsPage($team, $projectIds),
            'knowledge' => $this->knowledgePage($team, $projectIds),
            'data-sources' => $this->dataSourcesPage($team, $projectIds),
            'evaluations' => $this->evaluationsPage($team, $projectIds),
            'governance' => $this->governancePage($request, $team, $projectIds),
            'webhooks' => $this->webhooksPage($request, $team, $projectIds),
            'vault' => $this->vaultPage($team),
            'routing' => $this->routingPage($team, $projectIds),
            'identity' => $this->identityPage($team),
            'incidents' => $this->incidentsPage($request, $team, $projectIds),
            'settings' => GovernanceConsoleData::forTeam($team),
            default => [],
        };

        return [...$this->emptyContract(), ...$data];
    }

    /**
     * Null means platform-wide access; an array is the actor's active project scope.
     *
     * @return array<int, string>|null
     */
    private function authorizedProjectIds(User $user, Team $team): ?array
    {
        $access = app(MaaccAccess::class)->forUser($user, $team);

        return $access['isPlatformAdmin'] ? null : $access['projectIds'];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function dashboard(Team $team, ?array $projectIds): array
    {
        $aggregates = app(MaaccConsoleCache::class)->remember($team, 'dashboard:v2', 30, function () use ($team): array {
            $operational = app(OperationalMonitor::class)->forTeam($team);

            return [
                'dashboard' => [
                    ...app(RunMetrics::class)->forTeam($team),
                    'alerts' => $operational['alerts'],
                ],
                'operational' => $operational['metrics'],
            ];
        });

        $runs = $this->runQuery($team, $projectIds)
            ->with(['agent', 'application', 'project', 'llmProvider'])
            ->latest('created_at')
            ->limit(10)
            ->get();

        return [
            ...$aggregates,
            'apps' => $this->applications($team, $projectIds),
            'projects' => $this->projects($team, $projectIds),
            'runs' => AgentRunResource::collection($runs)->resolve(),
            'agents' => $this->agents($team, $projectIds, 10),
            'tools' => $this->tools($team, $projectIds, 10),
            'llms' => $this->llms($team, 10),
            'meta' => $this->meta('dashboard aggregates', 30),
        ];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function application(Team $team, ?array $projectIds, ?string $slug): array
    {
        $apps = collect($this->applications($team, $projectIds))->where('id', $slug)->values()->all();
        $allowedProjectIds = $this->projectIdsForApplication($team, $projectIds, $slug);

        return [
            'apps' => $apps,
            'projects' => $this->projects($team, $allowedProjectIds),
            'agents' => $this->agents($team, $allowedProjectIds),
            'tools' => $this->toolsForApplication($team, $slug),
            'llms' => $this->llms($team),
        ];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function projectsPage(Team $team, ?array $projectIds): array
    {
        return [
            'apps' => $this->applications($team, $projectIds),
            'projects' => $this->projects($team, $projectIds),
            'llms' => $this->llms($team),
            'memberDirectory' => $team->members()->orderBy('name')->limit(100)->get(['users.id', 'name', 'email'])->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ])->all(),
        ];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function agentsPage(Team $team, ?array $projectIds): array
    {
        return [
            'apps' => $this->applications($team, $projectIds),
            'projects' => $this->projects($team, $projectIds),
            'agents' => $this->agents($team, $projectIds),
            'llms' => $this->llms($team),
        ];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function agentCreatePage(Team $team, ?array $projectIds): array
    {
        return [
            ...$this->agentsPage($team, $projectIds),
            'tools' => $this->tools($team, $projectIds),
        ];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function agentPage(Team $team, ?array $projectIds, ?string $slug): array
    {
        $agent = $this->agentQuery($team, $projectIds)
            ->where('slug', $slug)
            ->with(['project.application', 'llmProvider', 'tools'])
            ->first();

        if (! $agent instanceof Agent) {
            return [];
        }

        $runs = $this->runQuery($team, $projectIds)
            ->where('agent_id', $agent->id)
            ->with(['agent', 'application', 'project', 'llmProvider'])
            ->latest('created_at')
            ->limit(25)
            ->get();

        return [
            'apps' => $this->applications($team, $projectIds, $agent->project->application->slug),
            'projects' => ProjectResource::collection(collect([$agent->project]))->resolve(),
            'agents' => AgentResource::collection(collect([$agent]))->resolve(),
            'tools' => ToolContractResource::collection($agent->tools)->resolve(),
            'runs' => AgentRunResource::collection($runs)->resolve(),
            'llms' => LlmProviderResource::collection(collect([$agent->llmProvider])->filter())->resolve(),
        ];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function toolsPage(Request $request, Team $team, ?array $projectIds): array
    {
        $query = $this->toolQuery($team, $projectIds)->with(['application', 'agents', 'implementations', 'mcpConnector', 'knowledgeSource', 'dataSource']);
        $this->applySearch($query, $request, ['name', 'slug', 'description']);
        $scope = strtolower($request->string('scope')->trim()->value());
        $mode = strtolower($request->string('mode')->trim()->value());

        if (in_array($scope, ['global', 'project', 'agent'], true)) {
            $query->where('scope', $scope);
        }

        if (in_array($mode, ['hosted', 'client', 'http', 'connector', 'knowledge', 'db'], true)) {
            $query->where('execution_mode', $mode);
        }

        $paginator = $query->orderBy('name')->orderBy('id')->cursorPaginate($this->pageSize($request), cursorName: 'tools_cursor');

        return [
            'apps' => $this->applications($team, $projectIds),
            'tools' => ToolContractResource::collection(collect($paginator->items()))->resolve(),
            'pagination' => ['tools' => $this->pagination($paginator, $request)],
        ];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function toolPage(Team $team, ?array $projectIds, ?string $slug): array
    {
        $tool = $this->toolQuery($team, $projectIds)
            ->where('slug', $slug)
            ->with(['application', 'agents.project.application', 'implementations', 'mcpConnector', 'knowledgeSource', 'dataSource'])
            ->first();

        if (! $tool instanceof ToolContract) {
            return [];
        }

        return [
            'apps' => $this->applications($team, $projectIds, $tool->application?->slug),
            'agents' => AgentResource::collection($tool->agents)->resolve(),
            'tools' => ToolContractResource::collection(collect([$tool]))->resolve(),
        ];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function sdkPage(Team $team, ?array $projectIds): array
    {
        return [
            'apps' => $this->applications($team, $projectIds),
            'tools' => $this->tools($team, $projectIds),
            'sdkCompatibility' => app(SdkCompatibilityReport::class)->forTeam($team),
        ];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function playgroundPage(Team $team, ?array $projectIds): array
    {
        return [
            ...$this->agentsPage($team, $projectIds),
            'tools' => $this->tools($team, $projectIds),
        ];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function runsPage(Request $request, Team $team, ?array $projectIds): array
    {
        $query = $this->runQuery($team, $projectIds)->with(['agent', 'application', 'project', 'llmProvider']);
        $this->applyRunFilters($query, $request);
        $paginator = $query->latest('created_at')->orderByDesc('id')->cursorPaginate($this->pageSize($request), cursorName: 'runs_cursor');
        $metrics = app(RunMetrics::class)->forTeam($team);

        return [
            'apps' => $this->applications($team, $projectIds),
            'agents' => $this->agents($team, $projectIds),
            'runs' => AgentRunResource::collection(collect($paginator->items()))->resolve(),
            'dashboard' => [...$this->emptyDashboard(), ...$metrics],
            'pagination' => ['runs' => $this->pagination($paginator, $request)],
            'meta' => $this->meta('agent_runs', 0),
        ];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function runPage(Team $team, ?array $projectIds, ?string $slug): array
    {
        $run = $this->runQuery($team, $projectIds)
            ->where('slug', $slug)
            ->with(['agent.project.application', 'application', 'project', 'llmProvider', 'toolCalls.toolContract'])
            ->first();

        if (! $run instanceof AgentRun) {
            return [];
        }

        return [
            'apps' => ApplicationResource::collection(collect([$run->application])->filter())->resolve(),
            'projects' => ProjectResource::collection(collect([$run->project])->filter())->resolve(),
            'agents' => AgentResource::collection(collect([$run->agent])->filter())->resolve(),
            'runs' => AgentRunResource::collection(collect([$run]))->resolve(),
            'llms' => LlmProviderResource::collection(collect([$run->llmProvider])->filter())->resolve(),
            'tools' => ToolContractResource::collection($run->toolCalls->pluck('toolContract')->filter()->unique('id')->values())->resolve(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function llmPage(Team $team): array
    {
        return ['llms' => $this->llms($team), 'providerCatalog' => ProviderCatalog::providers()];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function connectorsPage(Team $team, ?array $projectIds): array
    {
        $query = McpConnector::query()->where('team_id', $team->id)->with('application')->withCount('tools')->orderBy('name')->limit(100);
        $this->scopeApplicationQuery($query, $projectIds);

        return [
            'apps' => $this->applications($team, $projectIds),
            'connectors' => McpConnectorResource::collection($query->get())->resolve(),
        ];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function knowledgePage(Team $team, ?array $projectIds): array
    {
        $query = KnowledgeSource::query()
            ->where('team_id', $team->id)
            ->with(['application', 'documents' => function (Relation $relation): void {
                $relation->getQuery()->withCount('chunks')->latest()->limit(25);
            }])
            ->withCount('tools')
            ->orderBy('name')
            ->limit(100);
        $this->scopeApplicationQuery($query, $projectIds);

        return [
            'apps' => $this->applications($team, $projectIds),
            'knowledgeSources' => KnowledgeSourceResource::collection($query->get())->resolve(),
        ];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function dataSourcesPage(Team $team, ?array $projectIds): array
    {
        $query = DataSource::query()->where('team_id', $team->id)->with('application')->withCount('tools')->orderBy('name')->limit(100);
        $this->scopeApplicationQuery($query, $projectIds);

        return [
            'apps' => $this->applications($team, $projectIds),
            'dataSources' => DataSourceResource::collection($query->get())->resolve(),
        ];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function evaluationsPage(Team $team, ?array $projectIds): array
    {
        $datasets = EvaluationDataset::query()->where('team_id', $team->id)->with(['project', 'cases'])->withCount('cases')->orderBy('name');
        $evaluations = Evaluation::query()->where('team_id', $team->id)->with(['agent', 'dataset', 'results' => function (Relation $relation): void {
            $relation->getQuery()->with('run')->latest()->limit(100);
        }])->latest()->limit(50);

        if ($projectIds !== null) {
            $datasets->whereIn('project_id', $projectIds);
            $evaluations->whereHas('agent', fn (Builder $builder) => $builder->whereIn('project_id', $projectIds));
        }

        return [
            'projects' => $this->projects($team, $projectIds),
            'agents' => $this->agents($team, $projectIds),
            'evaluationDatasets' => EvaluationDatasetResource::collection($datasets->get())->resolve(),
            'evaluations' => EvaluationResource::collection($evaluations->get())->resolve(),
        ];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function governancePage(Request $request, Team $team, ?array $projectIds): array
    {
        $approvalQuery = ApprovalRequest::query()->where('team_id', $team->id)->pending()->with(['application', 'subject']);
        $auditQuery = AuditEvent::query()->where('team_id', $team->id)->with('actor');

        if ($projectIds !== null) {
            $approvalQuery->whereIn('project_id', $projectIds);
            $auditQuery->where(function (Builder $builder) use ($projectIds): void {
                $builder->whereIn('auditable_id', $projectIds)->orWhereJsonContains('metadata->project_id', $projectIds);
            });
        }

        $approvals = $approvalQuery->latest()->orderByDesc('id')->cursorPaginate($this->pageSize($request), cursorName: 'approvals_cursor');
        $audits = $auditQuery->latest()->orderByDesc('id')->cursorPaginate($this->pageSize($request), cursorName: 'audits_cursor');
        $approvalRows = collect(ApprovalRequestResource::collection(collect($approvals->items()))->resolve());
        $governance = GovernanceConsoleData::forTeam($team);

        return [
            ...$governance,
            'apps' => $this->applications($team, $projectIds),
            'projects' => $this->projects($team, $projectIds),
            'agents' => $this->agents($team, $projectIds),
            'llms' => $this->llms($team),
            'approvals' => [
                'tools' => $approvalRows->where('queue', 'tools')->values()->all(),
                'agents' => $approvalRows->where('queue', 'agents')->values()->all(),
                'models' => $approvalRows->where('queue', 'models')->values()->all(),
                'data' => $approvalRows->where('queue', 'data')->values()->all(),
                'runtime' => $approvalRows->where('queue', 'runtime')->values()->all(),
            ],
            'auditEvents' => AuditEventResource::collection(collect($audits->items()))->resolve(),
            'pagination' => [
                'approvals' => $this->pagination($approvals, $request),
                'audits' => $this->pagination($audits, $request),
            ],
        ];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function webhooksPage(Request $request, Team $team, ?array $projectIds): array
    {
        $query = WebhookEndpoint::query()
            ->whereHas('application', fn (Builder $builder) => $builder->where('team_id', $team->id))
            ->with(['application', 'deliveries' => function (Relation $relation): void {
                $relation->getQuery()->with('agentRun')->latest()->limit(15);
            }]);
        $this->scopeApplicationQuery($query, $projectIds);
        $this->applySearch($query, $request, ['name', 'url']);
        $paginator = $query->latest()->orderByDesc('id')->cursorPaginate($this->pageSize($request), cursorName: 'webhooks_cursor');

        return [
            'apps' => $this->applications($team, $projectIds),
            'webhooks' => WebhookEndpointResource::collection(collect($paginator->items()))->resolve(),
            'pagination' => ['webhooks' => $this->pagination($paginator, $request)],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function vaultPage(Team $team): array
    {
        $secrets = $team->vaultSecrets()->with(['creator', 'llmProviders'])->latest()->limit(100)->get();

        return ['vaultSecrets' => VaultSecretResource::collection($secrets)->resolve()];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function routingPage(Team $team, ?array $projectIds): array
    {
        $policies = $team->modelRoutingPolicies()->with(['agent', 'primaryProvider'])->orderBy('name');

        if ($projectIds !== null) {
            $policies->whereHas('agent', fn (Builder $builder) => $builder->whereIn('project_id', $projectIds));
        }

        $providers = $team->llmProviders()->limit(100)->get();
        $health = app(ProviderHealth::class)->forProviderIds($providers->pluck('id')->all());

        return [
            'agents' => $this->agents($team, $projectIds),
            'llms' => LlmProviderResource::collection($providers)->resolve(),
            'routingPolicies' => ModelRoutingPolicyResource::collection($policies->limit(100)->get())->resolve(),
            'providerHealth' => $providers->map(fn ($provider): array => [
                'id' => $provider->id,
                'name' => $provider->name,
                'code' => $provider->code,
                'sampleSize' => $health[$provider->id]->sampleSize,
                'failureRate' => $health[$provider->id]->failureRate,
                'healthy' => $health[$provider->id]->healthy,
                'avgLatencyMs' => $health[$provider->id]->avgLatencyMs,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function identityPage(Team $team): array
    {
        if (! request()->user()?->can('viewAny', SsoConnection::class)) {
            return [];
        }

        $connections = $team->ssoConnections()->withCount('identities')->orderBy('name')->limit(100)->get();

        return ['ssoConnections' => SsoConnectionResource::collection($connections)->resolve()];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function incidentsPage(Request $request, Team $team, ?array $projectIds): array
    {
        $query = IncidentAction::query()->where('team_id', $team->id)->with('actor');
        $this->applySearch($query, $request, ['subject_label', 'reason']);
        $paginator = $query->latest()->orderByDesc('id')->cursorPaginate($this->pageSize($request), cursorName: 'incidents_cursor');

        return [
            'apps' => $this->applications($team, $projectIds),
            'connectors' => $this->connectorsPage($team, $projectIds)['connectors'],
            'webhooks' => $this->webhooksPage($request, $team, $projectIds)['webhooks'],
            'incidents' => IncidentActionResource::collection(collect($paginator->items()))->resolve(),
            'pagination' => ['incidents' => $this->pagination($paginator, $request)],
        ];
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<int, mixed>
     */
    private function applications(Team $team, ?array $projectIds, ?string $slug = null): array
    {
        $query = $team->applications()->with('credentials')->orderBy('name');

        if ($projectIds !== null) {
            $query->whereHas('projects', fn (Builder $builder) => $builder->whereIn('id', $projectIds));
        }

        if ($slug !== null) {
            $query->where('slug', $slug);
        }

        return ApplicationResource::collection($query->limit(100)->get())->resolve();
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<int, mixed>
     */
    private function projects(Team $team, ?array $projectIds): array
    {
        $query = Project::query()
            ->whereHas('application', fn (Builder $builder) => $builder->where('team_id', $team->id))
            ->with(['application', 'llmProviders', 'projectMembers.user', 'projectMembers.grantor', 'projectMembers.revoker', 'projectMembers.certifier'])
            ->orderBy('name');

        if ($projectIds !== null) {
            $query->whereIn('id', $projectIds);
        }

        return ProjectResource::collection($query->limit(100)->get())->resolve();
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<int, mixed>
     */
    private function agents(Team $team, ?array $projectIds, int $limit = 100): array
    {
        return AgentResource::collection(
            $this->agentQuery($team, $projectIds)->with(['project.application', 'llmProvider', 'tools'])->orderBy('name')->limit($limit)->get()
        )->resolve();
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<int, mixed>
     */
    private function tools(Team $team, ?array $projectIds, int $limit = 100): array
    {
        return ToolContractResource::collection(
            $this->toolQuery($team, $projectIds)->with(['application', 'agents', 'implementations', 'mcpConnector', 'knowledgeSource', 'dataSource'])->orderBy('name')->limit($limit)->get()
        )->resolve();
    }

    /**
     * @return array<int, mixed>
     */
    private function toolsForApplication(Team $team, ?string $applicationSlug): array
    {
        return ToolContractResource::collection(
            $team->toolContracts()->whereHas('application', fn (Builder $builder) => $builder->where('slug', $applicationSlug))->with(['application', 'agents', 'implementations', 'mcpConnector', 'knowledgeSource', 'dataSource'])->orderBy('name')->limit(100)->get()
        )->resolve();
    }

    /**
     * @return array<int, mixed>
     */
    private function llms(Team $team, int $limit = 100): array
    {
        return LlmProviderResource::collection($team->llmProviders()->with('vaultSecret')->orderByDesc('usage_pct')->limit($limit)->get())->resolve();
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return Builder<Agent>
     */
    private function agentQuery(Team $team, ?array $projectIds): Builder
    {
        $query = Agent::query()->whereHas('project.application', fn (Builder $builder) => $builder->where('team_id', $team->id));

        return $projectIds === null ? $query : $query->whereIn('project_id', $projectIds);
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return Builder<AgentRun>
     */
    private function runQuery(Team $team, ?array $projectIds): Builder
    {
        $query = AgentRun::query()->whereHas('application', fn (Builder $builder) => $builder->where('team_id', $team->id));

        return $projectIds === null ? $query : $query->whereIn('project_id', $projectIds);
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return Builder<ToolContract>
     */
    private function toolQuery(Team $team, ?array $projectIds): Builder
    {
        $query = ToolContract::query()->where('team_id', $team->id);

        if ($projectIds !== null) {
            $query->where(function (Builder $builder) use ($projectIds): void {
                $builder->whereHas('assignments', fn (Builder $assignment) => $assignment->whereIn('tool_assignments.project_id', $projectIds))
                    ->orWhereHas('agents', fn (Builder $agent) => $agent->whereIn('agents.project_id', $projectIds));
            });
        }

        return $query;
    }

    /**
     * @param  Builder<*>  $query
     * @param  array<int, string>|null  $projectIds
     */
    private function scopeApplicationQuery(Builder $query, ?array $projectIds): void
    {
        if ($projectIds === null) {
            return;
        }

        $query->whereHas('application.projects', fn (Builder $builder) => $builder->whereIn('id', $projectIds));
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return array<int, string>
     */
    private function projectIdsForApplication(Team $team, ?array $projectIds, ?string $applicationSlug): array
    {
        $query = Project::query()->whereHas('application', fn (Builder $builder) => $builder->where('team_id', $team->id)->where('slug', $applicationSlug));

        if ($projectIds !== null) {
            $query->whereIn('id', $projectIds);
        }

        return $query->pluck('id')->all();
    }

    /**
     * @param  Builder<AgentRun>  $query
     */
    private function applyRunFilters(Builder $query, Request $request): void
    {
        $this->applySearch($query, $request, ['slug', 'caller', 'input']);

        foreach (['status', 'environment'] as $field) {
            $value = $request->string($field)->trim()->value();

            if ($value !== '' && $value !== 'All') {
                $query->where($field, $value);
            }
        }

        $application = $request->string('application')->trim()->value();
        $agent = $request->string('agent')->trim()->value();

        if ($application !== '' && $application !== 'All') {
            $query->whereHas('application', fn (Builder $builder) => $builder->where('slug', $application));
        }

        if ($agent !== '' && $agent !== 'All') {
            $query->whereHas('agent', fn (Builder $builder) => $builder->where('slug', $agent));
        }
    }

    /**
     * @param  Builder<*>  $query
     * @param  array<int, string>  $columns
     */
    private function applySearch(Builder $query, Request $request, array $columns): void
    {
        $search = mb_substr($request->string('q')->trim()->value(), 0, 100);

        if ($search === '') {
            return;
        }

        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
        $query->where(function (Builder $builder) use ($columns, $escaped): void {
            foreach ($columns as $index => $column) {
                $method = $index === 0 ? 'where' : 'orWhere';
                $builder->{$method}($column, 'like', "%{$escaped}%");
            }
        });
    }

    private function pageSize(Request $request): int
    {
        return min(self::MAX_PAGE_SIZE, max(10, $request->integer('per_page', self::DEFAULT_PAGE_SIZE)));
    }

    /**
     * @param  CursorPaginator<int, mixed>  $paginator
     * @return array<string, mixed>
     */
    private function pagination(CursorPaginator $paginator, Request $request): array
    {
        return [
            'count' => $paginator->count(),
            'perPage' => $paginator->perPage(),
            'hasMore' => $paginator->hasMorePages(),
            'nextCursor' => $paginator->nextCursor()?->encode(),
            'previousCursor' => $paginator->previousCursor()?->encode(),
            'filters' => $request->only(['q', 'status', 'environment', 'application', 'agent', 'scope', 'mode', 'per_page']),
        ];
    }

    /**
     * @return array{source: string, freshAt: string, cacheSeconds: int, timezone: string}
     */
    private function meta(string $source, int $cacheSeconds): array
    {
        return [
            'source' => $source,
            'freshAt' => now()->toIso8601String(),
            'cacheSeconds' => $cacheSeconds,
            'timezone' => config('app.timezone'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyContract(): array
    {
        return [
            'apps' => [],
            'projects' => [],
            'agents' => [],
            'tools' => [],
            'runs' => [],
            'llms' => [],
            'providerCatalog' => [],
            'memberDirectory' => [],
            'dashboard' => $this->emptyDashboard(),
            'operational' => ['totalRuns' => 0, 'failedRuns' => 0, 'expiredRuns' => 0, 'waitingRuns' => 0, 'avgLatencyMs' => 0, 'errorRate' => 0, 'toolFailureRate' => 0, 'costAnomaly' => false],
            'approvals' => ['tools' => [], 'agents' => [], 'models' => [], 'data' => [], 'runtime' => []],
            'auditEvents' => [],
            'roles' => [],
            'policies' => [],
            'governanceSettings' => ['retainPromptsDays' => 90, 'retainResponsesDays' => 90, 'retainToolArgumentsDays' => 30, 'retainToolResultsDays' => 30, 'auditRetentionDays' => 365, 'maskSensitiveInputs' => true, 'maskSensitiveOutputs' => true, 'toolResultHandling' => 'mask', 'blockRestrictedLogging' => true, 'defaultDailyRunQuota' => null],
            'quotas' => [],
            'sdkCompatibility' => ['platform' => ['api_version' => '0.0.1', 'minimum_client_version' => '0.0.1', 'current_client_version' => '0.0.1', 'languages' => [], 'packages' => [], 'deprecations' => []], 'applications' => [], 'drift' => []],
            'webhooks' => [],
            'connectors' => [],
            'knowledgeSources' => [],
            'dataSources' => [],
            'evaluationDatasets' => [],
            'evaluations' => [],
            'vaultSecrets' => [],
            'routingPolicies' => [],
            'providerHealth' => [],
            'incidents' => [],
            'ssoConnections' => [],
            'pagination' => [],
            'meta' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyDashboard(): array
    {
        return [
            'stats' => ['apps' => 0, 'projects' => 0, 'agents' => 0, 'tools' => 0, 'runsToday' => 0, 'waitingClient' => 0, 'success' => 0, 'failed' => 0, 'tokens' => '0', 'cost' => 'USD 0', 'costEstimated' => true],
            'runStatus' => [],
            'runsOverTime' => [],
            'topAgents' => [],
            'alerts' => [],
        ];
    }
}
