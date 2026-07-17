<?php

namespace App\Actions\Maacc;

use App\Enums\ToolScope;
use App\Models\Agent;
use App\Models\ToolAssignment;
use App\Support\Governance\TenantRelationshipGuard;
use Illuminate\Support\Facades\DB;

class SyncAgentTools
{
    public function __construct(private readonly TenantRelationshipGuard $relationships) {}

    /**
     * Sync agent-level tool assignments.
     *
     * @param  iterable<int, string>  $toolIds
     */
    public function handle(Agent $agent, iterable $toolIds, bool $replace = false): void
    {
        $toolIds = collect($toolIds)
            ->filter(fn (string $toolId): bool => $toolId !== '')
            ->unique()
            ->values()
            ->all();

        DB::transaction(function () use ($agent, $toolIds, $replace): void {
            $agent = $this->relationships->assertAgentTools($agent, $toolIds);

            if ($replace) {
                ToolAssignment::query()->where('agent_id', $agent->id)->delete();
            }

            foreach ($toolIds as $toolId) {
                ToolAssignment::firstOrCreate([
                    'tool_contract_id' => $toolId,
                    'agent_id' => $agent->id,
                ], [
                    'scope' => ToolScope::Agent->value,
                ]);
            }
        });
    }
}
