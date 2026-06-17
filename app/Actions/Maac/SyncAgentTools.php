<?php

namespace App\Actions\Maac;

use App\Enums\ToolScope;
use App\Models\Agent;
use App\Models\ToolAssignment;

class SyncAgentTools
{
    /**
     * Sync agent-level tool assignments.
     *
     * @param  iterable<int, string>  $toolIds
     */
    public function handle(Agent $agent, iterable $toolIds, bool $replace = false): void
    {
        if ($replace) {
            ToolAssignment::query()->where('agent_id', $agent->id)->delete();
        }

        foreach ($toolIds as $toolId) {
            ToolAssignment::create([
                'tool_contract_id' => $toolId,
                'agent_id' => $agent->id,
                'scope' => ToolScope::Agent->value,
            ]);
        }
    }
}
