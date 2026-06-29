<?php

namespace App\Support;

use App\Http\Resources\Maac\AgentResource;
use App\Http\Resources\Maac\AgentRunResource;
use App\Http\Resources\Maac\ApplicationResource;
use App\Http\Resources\Maac\DataSourceResource;
use App\Http\Resources\Maac\EvaluationDatasetResource;
use App\Http\Resources\Maac\EvaluationResource;
use App\Http\Resources\Maac\KnowledgeSourceResource;
use App\Http\Resources\Maac\LlmProviderResource;
use App\Http\Resources\Maac\McpConnectorResource;
use App\Http\Resources\Maac\ProjectResource;
use App\Http\Resources\Maac\ToolContractResource;
use App\Http\Resources\Maac\WebhookEndpointResource;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\DataSource;
use App\Models\Evaluation;
use App\Models\EvaluationDataset;
use App\Models\KnowledgeSource;
use App\Models\McpConnector;
use App\Models\Project;
use App\Models\Team;
use App\Models\WebhookEndpoint;
use App\Support\Observability\OperationalMonitor;
use App\Support\Observability\RunMetrics;
use App\Support\Sdk\SdkCompatibilityReport;

/**
 * Assembles the MAAC console dataset for a team as plain arrays matching the
 * Phase 1 fixture (resources/js/maac/data.ts). Shared with every console page
 * so the client-side scope/persona layer can filter real records instead of
 * the static mock data.
 */
class MaacConsoleData
{
    /**
     * Build the full console dataset for the given team.
     *
     * @return array<string, mixed>
     */
    public static function forTeam(Team $team): array
    {
        $applications = $team->applications()
            ->with('credentials')
            ->orderBy('name')
            ->get();

        $projects = Project::query()
            ->whereHas('application', fn ($query) => $query->where('team_id', $team->id))
            ->with(['application', 'llmProviders'])
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
}
