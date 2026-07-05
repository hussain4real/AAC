<?php

namespace App\Http\Controllers\Maacc;

use App\Enums\AgentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\StartPlaygroundRunRequest;
use App\Http\Requests\Maacc\SubmitPlaygroundToolResultRequest;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Support\Governance\IncidentGuard;
use App\Support\Runtime\AgentRunner;
use App\Support\Runtime\PlaygroundRunPayload;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

/**
 * Console runtime for the agent playground. A team member runs a published agent
 * straight from the console; the run is driven by the very same
 * {@see AgentRunner} the SDK uses, so the console exercises the real
 * model/tool loop end-to-end rather than a simulation. Each response carries the
 * SDK run envelope plus the run's trace timeline so the page can render it live.
 */
class PlaygroundRunController extends Controller
{
    /**
     * Start a synchronous run for the given agent and return its outcome. The run
     * blocks until it completes, pauses for a client-side tool, or fails.
     */
    public function store(StartPlaygroundRunRequest $request, IncidentGuard $incidents, AgentRunner $runner, string $currentTeam, Agent $agent): JsonResponse
    {
        Gate::authorize('run', $agent);

        $agent->loadMissing(['project.application', 'llmProvider', 'tools']);

        if ($agent->status !== AgentStatus::Published) {
            return new JsonResponse(['message' => 'The agent must be published before it can be run from the console.'], 422);
        }

        $application = $agent->project->application;
        $incidents->assert($application);

        $environment = $request->environment();

        if ($agent->project->environment !== $environment) {
            return new JsonResponse([
                'message' => 'The selected agent is not available in the '.$environment->label().' playground environment.',
            ], 422);
        }

        $run = $runner->start($agent, $application, $environment, $request->runInput(), $request->caller());

        return new JsonResponse(PlaygroundRunPayload::for($run), 201);
    }

    /**
     * Submit a client-side tool result to resume a run paused for the console
     * (the playground itself stands in for the calling application's SDK).
     */
    public function toolResult(SubmitPlaygroundToolResultRequest $request, AgentRunner $runner, string $currentTeam, AgentRun $run): JsonResponse
    {
        $run->loadMissing('agent.project.application');

        Gate::authorize('run', $run->agent);

        $resumed = $runner->resume($run, $request->toolCallId(), $request->result());

        return new JsonResponse(PlaygroundRunPayload::for($resumed));
    }
}
