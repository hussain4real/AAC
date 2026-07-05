<?php

namespace App\Http\Controllers\Maacc;

use App\Actions\Maacc\UpdateGovernanceSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\UpdateGovernanceSettingsRequest;
use App\Models\GovernanceSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class GovernanceSettingController extends Controller
{
    /**
     * Update the current team's governance settings (retention, masking, quota).
     */
    public function update(UpdateGovernanceSettingsRequest $request, UpdateGovernanceSettings $action): RedirectResponse
    {
        $team = $request->user()->currentTeam()->firstOrFail();
        $settings = GovernanceSetting::forTeam($team);

        Gate::authorize('update', $settings);

        $action->handle($settings, $request->validated(), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Governance settings updated.']);

        return back();
    }
}
