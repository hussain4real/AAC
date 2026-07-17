<?php

namespace App\Actions\Maacc;

use App\Models\Agent;
use App\Support\Governance\TenantRelationshipGuard;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateAgent
{
    /**
     * Create a new action instance.
     */
    public function __construct(
        private readonly SyncAgentTools $syncAgentTools,
        private readonly TenantRelationshipGuard $relationships,
    ) {}

    /**
     * Update an agent and replace its tool assignments when supplied.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Agent $agent, array $data): Agent
    {
        $data = Arr::only($data, [
            'llm_provider_id',
            'name',
            'system_prompt',
            'temperature',
            'max_tokens',
            'description',
            'sensitivity',
            'requires_runtime_approval',
            'tool_ids',
        ]);
        $shouldSyncTools = array_key_exists('tool_ids', $data);
        $toolIds = $data['tool_ids'] ?? [];
        unset($data['tool_ids']);

        return DB::transaction(function () use ($agent, $data, $shouldSyncTools, $toolIds): Agent {
            $agent = $this->relationships->agentForWrite($agent);
            $this->relationships->assertAgentRelationships($agent->project, [
                'llm_provider_id' => $data['llm_provider_id'] ?? $agent->llm_provider_id,
                'tool_ids' => $shouldSyncTools
                    ? $toolIds
                    : $agent->tools()->pluck('tool_contracts.id')->all(),
            ]);

            $agent->update($data);

            if ($shouldSyncTools) {
                $this->syncAgentTools->handle($agent, $toolIds, replace: true);
            }

            return $agent;
        });
    }
}
