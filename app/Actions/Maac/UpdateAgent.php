<?php

namespace App\Actions\Maac;

use App\Models\Agent;
use Illuminate\Support\Facades\DB;

class UpdateAgent
{
    /**
     * Create a new action instance.
     */
    public function __construct(private SyncAgentTools $syncAgentTools)
    {
        //
    }

    /**
     * Update an agent and replace its tool assignments when supplied.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Agent $agent, array $data): Agent
    {
        $shouldSyncTools = array_key_exists('tool_ids', $data);
        $toolIds = $data['tool_ids'] ?? [];
        unset($data['tool_ids']);

        return DB::transaction(function () use ($agent, $data, $shouldSyncTools, $toolIds): Agent {
            $agent->update($data);

            if ($shouldSyncTools) {
                $this->syncAgentTools->handle($agent, $toolIds, replace: true);
            }

            return $agent;
        });
    }
}
