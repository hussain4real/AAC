<?php

namespace App\Actions\Maacc;

use App\Models\Agent;
use App\Models\ModelRoutingPolicy;
use App\Models\Team;
use App\Support\Governance\TenantRelationshipGuard;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateModelRoutingPolicy
{
    public function __construct(private readonly TenantRelationshipGuard $relationships) {}

    /**
     * Create a policy only after every candidate is approved for the agent project.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data, int|string|null $createdBy): ModelRoutingPolicy
    {
        $data = Arr::only($data, [
            'agent_id',
            'name',
            'strategy',
            'primary_provider_id',
            'fallback_provider_ids',
            'max_cost_per_1k',
            'max_latency_ms',
            'enabled',
        ]);

        return DB::transaction(function () use ($team, $data, $createdBy): ModelRoutingPolicy {
            $agent = Agent::query()->whereKey((string) $data['agent_id'])->firstOrFail();
            $agent = $this->relationships->assertRoutingPolicyRelationships($team, $agent, $data);

            return $team->modelRoutingPolicies()->create([
                ...$data,
                'agent_id' => $agent->id,
                'created_by' => $createdBy,
            ]);
        });
    }
}
