<?php

namespace App\Http\Controllers\Maac;

use App\Actions\Maac\CreateToolContract;
use App\Actions\Maac\DeleteToolContract;
use App\Actions\Maac\UpdateToolContract;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StoreToolContractRequest;
use App\Http\Requests\Maac\UpdateToolContractRequest;
use App\Models\ToolContract;
use App\Support\Governance\ApprovalManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ToolContractController extends Controller
{
    /**
     * Create a new tool contract. A server-side egress tool that requires
     * approval is created inactive and opens a governance approval request.
     */
    public function store(StoreToolContractRequest $request, CreateToolContract $createToolContract, ApprovalManager $approvals): RedirectResponse
    {
        Gate::authorize('create', ToolContract::class);

        $team = $request->user()->currentTeam()->firstOrFail();
        $tool = $createToolContract->handle($team, $request->validated());

        if ($tool->requires_approval && $tool->isServerSide() && $tool->status !== 'Active') {
            $approvals->requestToolContractApproval($tool, $request->user());
        }

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
