<?php

namespace App\Http\Controllers\Maacc;

use App\Actions\Maacc\CreateLlmProvider;
use App\Actions\Maacc\DeleteLlmProvider;
use App\Actions\Maacc\PublishLlmProvider;
use App\Actions\Maacc\UpdateLlmProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\StoreLlmProviderRequest;
use App\Http\Requests\Maacc\UpdateLlmProviderRequest;
use App\Models\LlmProvider;
use App\Support\Runtime\LlmProviderVerifier;
use Illuminate\Http\JsonResponse;
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

    /**
     * Run a live connection check against the model and return the classified
     * result so the console can tell the operator whether the API key, the model
     * code, or the network is at fault.
     */
    public function verify(string $currentTeam, LlmProvider $llmProvider, LlmProviderVerifier $verifier): JsonResponse
    {
        Gate::authorize('verify', $llmProvider);

        $result = $verifier->verify($llmProvider);

        return response()->json([
            'result' => $result->toArray(),
            'verification' => [
                'status' => $llmProvider->verification_status?->value,
                'message' => $llmProvider->verification_message,
                'verified_at' => $llmProvider->verified_at?->toIso8601String(),
                'checked_at' => $llmProvider->verification_checked_at?->toIso8601String(),
            ],
        ]);
    }

    /**
     * Publish the model to the live catalog. Publishing runs a fresh connection
     * check first and refuses to approve a model that does not pass, so a broken
     * key or model code can never reach production traffic.
     */
    public function publish(string $currentTeam, LlmProvider $llmProvider, LlmProviderVerifier $verifier, PublishLlmProvider $publishLlmProvider): RedirectResponse
    {
        Gate::authorize('publish', $llmProvider);

        $result = $verifier->verify($llmProvider);

        if (! $result->passed()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Cannot publish — '.$result->message]);

            return back();
        }

        $publishLlmProvider->handle($llmProvider);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Model published.']);

        return back();
    }
}
