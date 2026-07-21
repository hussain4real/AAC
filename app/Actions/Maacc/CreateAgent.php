<?php

namespace App\Actions\Maacc;

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\Project;
use App\Support\Governance\TenantRelationshipGuard;
use App\Support\Slug;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateAgent
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private readonly SyncAgentTools $syncAgentTools,
        private readonly TenantRelationshipGuard $relationships,
    ) {}

    /**
     * Create a draft agent with its initial version snapshot.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Project $project, array $data): Agent
    {
        $data = Arr::only($data, [
            'llm_provider_id',
            'name',
            'agent_slug',
            'system_prompt',
            'temperature',
            'max_tokens',
            'description',
            'sensitivity',
            'requires_runtime_approval',
            'tool_ids',
        ]);
        $toolIds = $data['tool_ids'] ?? [];
        unset($data['tool_ids']);

        return DB::transaction(function () use ($project, $data, $toolIds): Agent {
            $project = $this->relationships->assertAgentRelationships($project, [
                ...$data,
                'tool_ids' => $toolIds,
            ]);

            $agent = Agent::create([
                ...$data,
                'project_id' => $project->id,
                'slug' => Slug::unique('agents', (string) $data['agent_slug']),
                'status' => AgentStatus::Draft->value,
                'version' => 'v1',
            ]);

            $version = $agent->versions()->create([
                'version' => 'v1',
                'system_prompt' => $agent->system_prompt,
                'llm_provider_id' => $agent->llm_provider_id,
                'temperature' => $agent->temperature,
                'max_tokens' => $agent->max_tokens,
                'settings' => ['temperature' => $agent->temperature, 'max_tokens' => $agent->max_tokens],
                'status' => $agent->status->value,
            ]);

            $agent->update(['current_version_id' => $version->id]);
            $this->syncAgentTools->handle($agent, $toolIds);

            return $agent;
        });
    }
}
