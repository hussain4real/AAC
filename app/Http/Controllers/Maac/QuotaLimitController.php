<?php

namespace App\Http\Controllers\Maac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StoreQuotaLimitRequest;
use App\Http\Requests\Maac\UpdateQuotaLimitRequest;
use App\Models\QuotaLimit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class QuotaLimitController extends Controller
{
    /**
     * Create a rate limit / quota for the current team.
     */
    public function store(StoreQuotaLimitRequest $request): RedirectResponse
    {
        Gate::authorize('create', QuotaLimit::class);

        $team = $request->user()->currentTeam()->firstOrFail();
        $team->quotaLimits()->create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Quota created.']);

        return back();
    }

    /**
     * Update the given quota.
     */
    public function update(UpdateQuotaLimitRequest $request, string $currentTeam, QuotaLimit $quotaLimit): RedirectResponse
    {
        Gate::authorize('update', $quotaLimit);

        $quotaLimit->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Quota updated.']);

        return back();
    }

    /**
     * Delete the given quota.
     */
    public function destroy(Request $request, string $currentTeam, QuotaLimit $quotaLimit): RedirectResponse
    {
        Gate::authorize('delete', $quotaLimit);

        $quotaLimit->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Quota removed.']);

        return back();
    }
}
