<?php

namespace App\Http\Controllers\Maac;

use App\Actions\Maac\CreateAgent;
use App\Actions\Maac\DeleteAgent;
use App\Actions\Maac\PublishAgent;
use App\Actions\Maac\UpdateAgent;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StoreAgentRequest;
use App\Http\Requests\Maac\UpdateAgentRequest;
use App\Models\Agent;
use App\Models\Project;
use App\Models\User;
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
     * Publish the agent, snapshotting its configuration into a new version.
     */
    public function publish(Request $request, string $currentTeam, Agent $agent, PublishAgent $publishAgent): RedirectResponse
    {
        Gate::authorize('publish', $agent);

        /** @var User $publisher */
        $publisher = $request->user();
        $publishAgent->handle($agent, $publisher);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agent published.']);

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
