<?php

namespace App\Http\Controllers\Maacc;

use App\Actions\Maacc\CreateAgent;
use App\Actions\Maacc\DeleteAgent;
use App\Actions\Maacc\UpdateAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\StoreAgentRequest;
use App\Http\Requests\Maacc\UpdateAgentRequest;
use App\Models\Agent;
use App\Models\Project;
use App\Models\User;
use App\Support\Governance\ApprovalManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AgentController extends Controller
{
    /**
     * Create a new draft agent under a project, with an initial version.
     */
    public function store(StoreAgentRequest $request, CreateAgent $createAgent): RedirectResponse
    {
        $project = Project::findOrFail($request->string('project_id')->value());

        Gate::authorize('create', [Agent::class, $project]);

        $createAgent->handle($project, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agent created.']);

        return back();
    }

    /**
     * Update the given agent's configuration.
     */
    public function update(UpdateAgentRequest $request, string $currentTeam, Agent $agent, UpdateAgent $updateAgent): RedirectResponse
    {
        Gate::authorize('update', $agent);

        $updateAgent->handle($agent, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agent updated.']);

        return back();
    }

    /**
     * Publish the agent, snapshotting its configuration into a new version. The
     * promotion gate blocks publication while a required evaluation has not passed.
     */
    public function publish(Request $request, string $currentTeam, Agent $agent, ApprovalManager $approvals): RedirectResponse
    {
        Gate::authorize('publish', $agent);

        /** @var User $publisher */
        $publisher = $request->user();
        $agent->loadMissing('project');
        $approvals->requestAgentPublication($agent, $publisher, $agent->project->environment);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agent publication approval requested.']);

        return back();
    }

    /**
     * Delete (soft delete) the given agent.
     */
    public function destroy(Request $request, string $currentTeam, Agent $agent, DeleteAgent $deleteAgent): RedirectResponse
    {
        Gate::authorize('delete', $agent);

        $deleteAgent->handle($agent);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agent deleted.']);

        return back();
    }
}
