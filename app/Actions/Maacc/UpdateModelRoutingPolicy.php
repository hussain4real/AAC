<?php

namespace App\Actions\Maacc;

use App\Models\Agent;
use App\Models\ModelRoutingPolicy;
use App\Support\Governance\TenantRelationshipGuard;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateModelRoutingPolicy
{
    public function __construct(private readonly TenantRelationshipGuard $relationships) {}

    /**
     * Update a policy without allowing candidate or tenant re-parenting.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(ModelRoutingPolicy $policy, array $data): ModelRoutingPolicy
    {
        $data = Arr::only($data, [
            'name',
            'strategy',
            'primary_provider_id',
            'fallback_provider_ids',
            'max_cost_per_1k',
            'max_latency_ms',
            'enabled',
        ]);

        return DB::transaction(function () use ($policy, $data): ModelRoutingPolicy {
            $policy = ModelRoutingPolicy::query()
                ->with('team')
                ->whereKey($policy->getKey())
                ->lockForUpdate()
                ->firstOrFail();
            $agent = Agent::query()->whereKey($policy->agent_id)->firstOrFail();

            $this->relationships->assertRoutingPolicyRelationships($policy->team, $agent, $data, $policy);
            $policy->update($data);

            return $policy;
        });
    }
}
