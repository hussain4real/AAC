<?php

namespace App\Http\Controllers\Maac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StoreEvaluationDatasetRequest;
use App\Http\Requests\Maac\UpdateEvaluationDatasetRequest;
use App\Models\EvaluationDataset;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Console management of golden evaluation datasets.
 */
class EvaluationDatasetController extends Controller
{
    /**
     * Create a new golden dataset.
     */
    public function store(StoreEvaluationDatasetRequest $request): RedirectResponse
    {
        Gate::authorize('create', EvaluationDataset::class);

        $team = $request->user()->currentTeam()->firstOrFail();

        EvaluationDataset::create([
            ...$request->validated(),
            'team_id' => $team->id,
            'slug' => Slug::unique('evaluation_datasets', $request->validated('name')),
            'created_by' => $request->user()?->getAuthIdentifier(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evaluation dataset created.']);

        return back();
    }

    /**
     * Update the given dataset.
     */
    public function update(UpdateEvaluationDatasetRequest $request, string $currentTeam, EvaluationDataset $evaluationDataset): RedirectResponse
    {
        Gate::authorize('update', $evaluationDataset);

        $evaluationDataset->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evaluation dataset updated.']);

        return back();
    }

    /**
     * Delete (soft delete) the given dataset.
     */
    public function destroy(Request $request, string $currentTeam, EvaluationDataset $evaluationDataset): RedirectResponse
    {
        Gate::authorize('delete', $evaluationDataset);

        $evaluationDataset->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evaluation dataset deleted.']);

        return back();
    }
}
