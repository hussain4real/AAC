<?php

namespace App\Http\Controllers\Maac;

use App\Concerns\RecordsMaacAudit;
use App\Enums\AgentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StoreAgentRequest;
use App\Http\Requests\Maac\UpdateAgentRequest;
use App\Models\Agent;
use App\Models\Project;
use App\Models\ToolAssignment;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AgentController extends Controller
{
    use RecordsMaacAudit;

    /**
     * Create a new draft agent under a project, with an initial version.
     */
    public function store(StoreAgentRequest $request): RedirectResponse
    {
        $project = Project::findOrFail($request->string('project_id')->value());

        Gate::authorize('create', [Agent::class, $project]);

        $validated = $request->validated();

        $agent = Agent::create([
            ...$validated,
            'slug' => Slug::unique('agents', $request->string('agent_slug')->value()),
            'status' => $validated['status'] ?? AgentStatus::Draft->value,
            'version' => 'v1',
        ]);

        $version = $agent->versions()->create([
            'version' => 'v1',
            'system_prompt' => $agent->system_prompt,
            'llm_provider_id' => $agent->llm_provider_id,
            'temperature' => $agent->temperature,
            'max_tokens' => $agent->max_tokens,
            'settings' => ['temperature' => $agent->temperature, 'max_tokens' => $agent->max_tokens],
            'status' => $agent->status->value,
        ]);
        $agent->update(['current_version_id' => $version->id]);

        $this->syncTools($agent, $request);

        $this->recordAudit($request, 'agent.created', $agent, ['name' => $agent->name]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agent created.']);

        return back();
    }

    /**
     * Update the given agent's configuration.
     */
    public function update(UpdateAgentRequest $request, string $currentTeam, Agent $agent): RedirectResponse
    {
        Gate::authorize('update', $agent);

        $agent->update($request->validated());

        if ($request->has('tool_ids')) {
            ToolAssignment::query()->where('agent_id', $agent->id)->delete();
            $this->syncTools($agent, $request);
        }

        $this->recordAudit($request, 'agent.updated', $agent);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agent updated.']);

        return back();
    }

    /**
     * Publish the agent, snapshotting its configuration into a new version.
     */
    public function publish(Request $request, string $currentTeam, Agent $agent): RedirectResponse
    {
        Gate::authorize('publish', $agent);

        $nextVersion = 'v'.((int) ltrim($agent->version, 'v') + 1);
        $publishedAt = Carbon::now();

        $version = $agent->versions()->create([
            'version' => $nextVersion,
            'system_prompt' => $agent->system_prompt,
            'llm_provider_id' => $agent->llm_provider_id,
            'temperature' => $agent->temperature,
            'max_tokens' => $agent->max_tokens,
            'settings' => ['temperature' => $agent->temperature, 'max_tokens' => $agent->max_tokens],
            'status' => AgentStatus::Published->value,
            'published_at' => $publishedAt,
            'published_by' => $request->user()->id,
        ]);

        $agent->update([
            'status' => AgentStatus::Published->value,
            'published_at' => $publishedAt,
            'version' => $nextVersion,
            'current_version_id' => $version->id,
        ]);

        $this->recordAudit($request, 'agent.published', $agent, ['version' => $nextVersion]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agent published.']);

        return back();
    }

    /**
     * Delete (soft delete) the given agent.
     */
    public function destroy(Request $request, string $currentTeam, Agent $agent): RedirectResponse
    {
        Gate::authorize('delete', $agent);

        $agent->delete();

        $this->recordAudit($request, 'agent.deleted', $agent);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Agent deleted.']);

        return back();
    }

    /**
     * Create agent-level tool assignments from the request's tool ids.
     */
    private function syncTools(Agent $agent, Request $request): void
    {
        foreach ($request->collect('tool_ids') as $toolId) {
            ToolAssignment::create([
                'tool_contract_id' => $toolId,
                'agent_id' => $agent->id,
                'scope' => 'agent',
            ]);
        }
    }
}
