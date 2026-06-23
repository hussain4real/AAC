<?php

namespace App\Support;

use App\Http\Resources\Maac\AgentResource;
use App\Http\Resources\Maac\AgentRunResource;
use App\Http\Resources\Maac\ApplicationResource;
use App\Http\Resources\Maac\LlmProviderResource;
use App\Http\Resources\Maac\McpConnectorResource;
use App\Http\Resources\Maac\ProjectResource;
use App\Http\Resources\Maac\ToolContractResource;
use App\Http\Resources\Maac\WebhookEndpointResource;
use App\Models\Agent;
use App\Models\AgentRun;
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
            ->with(['application', 'agents', 'implementations', 'mcpConnector'])
            ->orderBy('name')
            ->get();

        $connectors = McpConnector::query()
            ->where('team_id', $team->id)
            ->with('application')
            ->withCount('tools')
            ->orderBy('name')
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
            ...GovernanceConsoleData::forTeam($team),
        ];
    }
}
