<?php

namespace App\Support;

use App\Http\Resources\Maac\AgentResource;
use App\Http\Resources\Maac\AgentRunResource;
use App\Http\Resources\Maac\ApplicationResource;
use App\Http\Resources\Maac\LlmProviderResource;
use App\Http\Resources\Maac\ProjectResource;
use App\Http\Resources\Maac\ToolContractResource;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\Team;

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
     * @return array{
     *     apps: array<int, array<string, mixed>>,
     *     projects: array<int, array<string, mixed>>,
     *     agents: array<int, array<string, mixed>>,
     *     tools: array<int, array<string, mixed>>,
     *     runs: array<int, array<string, mixed>>,
     *     llms: array<int, array<string, mixed>>,
     * }
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
            ->with(['application', 'agents'])
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

        return [
            'apps' => ApplicationResource::collection($applications)->resolve(),
            'projects' => ProjectResource::collection($projects)->resolve(),
            'agents' => AgentResource::collection($agents)->resolve(),
            'tools' => ToolContractResource::collection($tools)->resolve(),
            'runs' => AgentRunResource::collection($runs)->resolve(),
            'llms' => LlmProviderResource::collection($llms)->resolve(),
        ];
    }
}
