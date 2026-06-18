<?php

namespace App\Support\Runtime;

use App\Enums\AgentStatus;
use App\Exceptions\Sdk\RuntimeRequestException;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;

/**
 * Resolves and authorizes the runtime resources behind an SDK request, scoping
 * every lookup to the authenticated application so one application can never
 * invoke or inspect another's agents and runs.
 */
class RunAuthorizer
{
    /**
     * Resolve a published agent the application is allowed to invoke.
     *
     * @throws RuntimeRequestException
     */
    public function resolveAgent(Application $application, string $agentSlug): Agent
    {
        $agent = Agent::query()
            ->where('agent_slug', $agentSlug)
            ->whereHas('project', fn ($query) => $query->where('application_id', $application->id))
            ->with(['tools', 'llmProvider'])
            ->first();

        if (! $agent instanceof Agent) {
            throw RuntimeRequestException::agentNotFound();
        }

        if ($agent->status !== AgentStatus::Published) {
            throw RuntimeRequestException::agentNotPublished();
        }

        return $agent;
    }

    /**
     * Resolve a run the application owns.
     *
     * @throws RuntimeRequestException
     */
    public function resolveRun(Application $application, string $runSlug): AgentRun
    {
        $run = AgentRun::query()
            ->where('slug', $runSlug)
            ->where('application_id', $application->id)
            ->with(['agent.tools', 'agent.llmProvider'])
            ->first();

        if (! $run instanceof AgentRun) {
            throw RuntimeRequestException::runNotFound();
        }

        return $run;
    }
}
