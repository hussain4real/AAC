<?php

namespace App\Policies;

use App\Enums\MaacPermission;
use App\Models\Agent;
use App\Models\Project;
use App\Models\User;

/**
 * Agents are managed by Platform Admins and the Project Owners/Developers of the
 * project the agent belongs to. Publication is restricted to Project Owners.
 */
class AgentPolicy
{
    /**
     * Determine whether the user can view any agents.
     */
    public function viewAny(User $user): bool
    {
        return $user->currentTeam !== null;
    }

    /**
     * Determine whether the user can view the agent.
     */
    public function view(User $user, Agent $agent): bool
    {
        return $user->belongsToTeam($agent->project->application->team);
    }

    /**
     * Determine whether the user can create an agent, optionally scoped to the
     * target project.
     */
    public function create(User $user, ?Project $project = null): bool
    {
        if ($project !== null) {
            return $user->hasMaacPermission($project->application->team, MaacPermission::ManageAgent, $project);
        }

        $team = $user->currentTeam;

        return $team !== null && $user->hasMaacPermissionOnAnyProject($team, MaacPermission::ManageAgent);
    }

    /**
     * Determine whether the user can update the agent.
     */
    public function update(User $user, Agent $agent): bool
    {
        return $user->hasMaacPermission($agent->project->application->team, MaacPermission::ManageAgent, $agent->project);
    }

    /**
     * Determine whether the user can publish the agent to production.
     */
    public function publish(User $user, Agent $agent): bool
    {
        return $user->hasMaacPermission($agent->project->application->team, MaacPermission::PublishAgent, $agent->project);
    }

    /**
     * Determine whether the user can delete the agent.
     */
    public function delete(User $user, Agent $agent): bool
    {
        return $user->hasMaacPermission($agent->project->application->team, MaacPermission::ManageAgent, $agent->project);
    }
}
