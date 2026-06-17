<?php

namespace App\Http\Controllers\Maac;

use App\Actions\Maac\CreateToolContract;
use App\Actions\Maac\DeleteToolContract;
use App\Actions\Maac\UpdateToolContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StoreToolContractRequest;
use App\Http\Requests\Maac\UpdateToolContractRequest;
use App\Models\ToolContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ToolContractController extends Controller
{
    /**
     * Create a new tool contract.
     */
    public function store(StoreToolContractRequest $request, CreateToolContract $createToolContract): RedirectResponse
    {
        Gate::authorize('create', ToolContract::class);

        $team = $request->user()->currentTeam()->firstOrFail();
        $createToolContract->handle($team, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tool contract created.']);

        return back();
    }

    /**
     * Update the given tool contract.
     */
    public function update(UpdateToolContractRequest $request, string $currentTeam, ToolContract $tool, UpdateToolContract $updateToolContract): RedirectResponse
    {
        Gate::authorize('update', $tool);

        $updateToolContract->handle($tool, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tool contract updated.']);

        return back();
    }

    /**
     * Delete (soft delete) the given tool contract.
     */
    public function destroy(Request $request, string $currentTeam, ToolContract $tool, DeleteToolContract $deleteToolContract): RedirectResponse
    {
        Gate::authorize('delete', $tool);

        $deleteToolContract->handle($tool);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Tool contract deleted.']);

        return back();
    }
}
