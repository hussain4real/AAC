<?php

namespace App\Http\Controllers\Maac;

use App\Enums\Environment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StoreEvaluationRequest;
use App\Models\Agent;
use App\Models\Evaluation;
use App\Models\EvaluationDataset;
use App\Models\User;
use App\Support\Evaluation\EvaluationRunner;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Console management of evaluation runs: run a golden dataset against an agent
 * (optionally flagging it as a promotion-gating requirement) and remove a run.
 */
class EvaluationController extends Controller
{
    /**
     * Run a golden dataset against an agent and record the outcome.
     */
    public function store(StoreEvaluationRequest $request, EvaluationRunner $runner): RedirectResponse
    {
        Gate::authorize('create', Evaluation::class);

        $dataset = EvaluationDataset::findOrFail((string) $request->validated('evaluation_dataset_id'));
        $agent = Agent::findOrFail((string) $request->validated('agent_id'));

        /** @var User $user */
        $user = $request->user();

        $evaluation = $runner->run(
            $dataset,
            $agent,
            $user,
            Environment::from($request->validated('environment')),
            (bool) $request->validated('is_required', false),
        );

        Inertia::flash('toast', [
            'type' => $evaluation->hasPassed() ? 'success' : 'error',
            'message' => "Evaluation {$evaluation->status->label()} — {$evaluation->cases_passed}/{$evaluation->cases_total} cases passed.",
        ]);

        return back();
    }

    /**
     * Delete the given evaluation run.
     */
    public function destroy(Request $request, string $currentTeam, Evaluation $evaluation): RedirectResponse
    {
        Gate::authorize('delete', $evaluation);

        $evaluation->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evaluation deleted.']);

        return back();
    }
}
