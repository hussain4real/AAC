<?php

namespace App\Actions\Maac;

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\Project;
use App\Support\Slug;
use Illuminate\Support\Facades\DB;

class CreateAgent
{
    /**
     * Create a new action instance.
     */
    public function __construct(private SyncAgentTools $syncAgentTools)
    {
        //
    }

    /**
     * Create a draft agent with its initial version snapshot.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Project $project, array $data): Agent
    {
        $toolIds = $data['tool_ids'] ?? [];
        unset($data['tool_ids']);

        return DB::transaction(function () use ($project, $data, $toolIds): Agent {
            $agent = Agent::create([
                ...$data,
                'project_id' => $project->id,
                'slug' => Slug::unique('agents', (string) $data['agent_slug']),
                'status' => $data['status'] ?? AgentStatus::Draft->value,
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
