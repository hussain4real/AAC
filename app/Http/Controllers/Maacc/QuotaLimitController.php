<?php

namespace App\Http\Controllers\Maacc;

use App\Actions\Maacc\CreateQuotaLimit;
use App\Actions\Maacc\UpdateQuotaLimit;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\StoreQuotaLimitRequest;
use App\Http\Requests\Maacc\UpdateQuotaLimitRequest;
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
    public function store(StoreQuotaLimitRequest $request, CreateQuotaLimit $createQuotaLimit): RedirectResponse
    {
        Gate::authorize('create', QuotaLimit::class);

        $team = $request->user()->currentTeam()->firstOrFail();
        $createQuotaLimit->handle($team, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Quota created.']);

        return back();
    }

    /**
     * Update the given quota.
     */
    public function update(UpdateQuotaLimitRequest $request, string $currentTeam, QuotaLimit $quotaLimit, UpdateQuotaLimit $updateQuotaLimit): RedirectResponse
    {
        Gate::authorize('update', $quotaLimit);

        $updateQuotaLimit->handle($quotaLimit, $request->validated());

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
