<?php

namespace App\Http\Controllers\Maacc;

use App\Actions\Maacc\CreateModelRoutingPolicy;
use App\Actions\Maacc\UpdateModelRoutingPolicy;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\StoreModelRoutingPolicyRequest;
use App\Http\Requests\Maacc\UpdateModelRoutingPolicyRequest;
use App\Models\ModelRoutingPolicy;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Console management of advanced model routing policies: define how an agent
 * selects its model (strategy, candidate chain, cost/latency ceilings), edit it,
 * and remove it. The runtime applies the policy and records the decision on the
 * run trace.
 */
class ModelRoutingPolicyController extends Controller
{
    /**
     * Create a routing policy for an agent.
     */
    public function store(StoreModelRoutingPolicyRequest $request, CreateModelRoutingPolicy $createPolicy): RedirectResponse
    {
        Gate::authorize('create', ModelRoutingPolicy::class);

        $team = $request->user()->currentTeam()->firstOrFail();

        $createPolicy->handle($team, $request->validated(), $request->user()?->getAuthIdentifier());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Routing policy created.']);

        return back();
    }

    /**
     * Update the given routing policy.
     */
    public function update(
        UpdateModelRoutingPolicyRequest $request,
        UpdateModelRoutingPolicy $updatePolicy,
        string $currentTeam,
        ModelRoutingPolicy $modelRoutingPolicy,
    ): RedirectResponse {
        Gate::authorize('update', $modelRoutingPolicy);

        $updatePolicy->handle($modelRoutingPolicy, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Routing policy updated.']);

        return back();
    }

    /**
     * Delete the given routing policy.
     */
    public function destroy(Request $request, string $currentTeam, ModelRoutingPolicy $modelRoutingPolicy): RedirectResponse
    {
        Gate::authorize('delete', $modelRoutingPolicy);

        $modelRoutingPolicy->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Routing policy removed.']);

        return back();
    }
}
