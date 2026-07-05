<?php

namespace App\Http\Controllers\Maacc;

use App\Actions\Maacc\CreateLlmProvider;
use App\Actions\Maacc\DeleteLlmProvider;
use App\Actions\Maacc\UpdateLlmProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\StoreLlmProviderRequest;
use App\Http\Requests\Maacc\UpdateLlmProviderRequest;
use App\Models\LlmProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class LlmProviderController extends Controller
{
    /**
     * Add a model to the approved LLM catalog.
     */
    public function store(StoreLlmProviderRequest $request, CreateLlmProvider $createLlmProvider): RedirectResponse
    {
        Gate::authorize('create', LlmProvider::class);

        $team = $request->user()->currentTeam()->firstOrFail();
        $createLlmProvider->handle($team, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Model added to the catalog.']);

        return back();
    }

    /**
     * Update the given catalog model.
     */
    public function update(UpdateLlmProviderRequest $request, string $currentTeam, LlmProvider $llmProvider, UpdateLlmProvider $updateLlmProvider): RedirectResponse
    {
        Gate::authorize('update', $llmProvider);

        $updateLlmProvider->handle($llmProvider, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Model updated.']);

        return back();
    }

    /**
     * Remove the given model from the catalog.
     */
    public function destroy(Request $request, string $currentTeam, LlmProvider $llmProvider, DeleteLlmProvider $deleteLlmProvider): RedirectResponse
    {
        Gate::authorize('delete', $llmProvider);

        $deleteLlmProvider->handle($llmProvider);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Model removed.']);

        return back();
    }
}
