<?php

namespace App\Support;

use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\Project;
use App\Models\Team;
use App\Models\WebhookEndpoint;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

/** Tenant-scoped versioned cache for expensive console page aggregates. */
class MaaccConsoleCache
{
    /** @param Closure(): mixed $resolver */
    public function remember(Team $team, string $segment, int $seconds, Closure $resolver): mixed
    {
        $version = (int) Cache::get($this->versionKey($team->id), 1);

        return Cache::remember(
            "maacc:console:team:{$team->id}:v{$version}:{$segment}",
            $seconds,
            $resolver,
        );
    }

    public function invalidateTeam(int $teamId): void
    {
        Cache::add($this->versionKey($teamId), 1);
        Cache::increment($this->versionKey($teamId));
    }

    /** Invalidate only the tenant that owns the changed console model. */
    public function invalidateForModel(Model $model): void
    {
        $teamId = $this->teamIdFor($model);

        if ($teamId !== null) {
            $this->invalidateTeam($teamId);
        }
    }

    private function teamIdFor(Model $model): ?int
    {
        $direct = $model->getAttribute('team_id');

        if (is_numeric($direct)) {
            return (int) $direct;
        }

        if ($model instanceof Team) {
            return (int) $model->getKey();
        }

        $applicationId = $model->getAttribute('application_id');

        if (is_string($applicationId)) {
            return Application::withTrashed()->whereKey($applicationId)->value('team_id');
        }

        $projectId = $model->getAttribute('project_id');

        if (is_string($projectId)) {
            return Project::withTrashed()->whereKey($projectId)->with('application')->first()?->application?->team_id;
        }

        $agentId = $model->getAttribute('agent_id');

        if (is_string($agentId)) {
            return Agent::withTrashed()->whereKey($agentId)->with('project.application')->first()?->project?->application?->team_id;
        }

        $runId = $model->getAttribute('agent_run_id');

        if (is_string($runId)) {
            return AgentRun::whereKey($runId)->with('application')->first()?->application?->team_id;
        }

        $webhookId = $model->getAttribute('webhook_endpoint_id');

        if (is_string($webhookId)) {
            return WebhookEndpoint::whereKey($webhookId)->with('application')->first()?->application?->team_id;
        }

        return null;
    }

    private function versionKey(int $teamId): string
    {
        return "maacc:console:team:{$teamId}:version";
    }
}
