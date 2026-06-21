<?php

namespace App\Support\Governance;

use App\Enums\Environment;
use App\Enums\QuotaScope;
use App\Exceptions\Sdk\RuntimeRequestException;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\GovernanceSetting;
use App\Models\QuotaLimit;
use App\Models\Team;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

/**
 * Enforces per-day run and token quotas before a run is created. Quotas can be
 * scoped by platform, application, project, agent, or model and narrowed to a
 * single environment, plus a default per-environment run quota from the team's
 * governance settings.
 */
class QuotaGuard
{
    /**
     * Assert that creating a run for the agent stays within every applicable
     * quota, throwing a controlled error otherwise.
     *
     * @throws RuntimeRequestException
     */
    public function assert(Application $application, Agent $agent, Environment $environment): void
    {
        $team = $application->team;

        $this->assertDefaultQuota($team, $environment);

        $team->quotaLimits()->enabled()->get()
            ->filter(fn (QuotaLimit $quota): bool => $quota->appliesToEnvironment($environment) && $this->matches($quota, $application, $agent))
            ->each(fn (QuotaLimit $quota) => $this->enforce($quota, $application, $agent));
    }

    /**
     * Enforce the team-wide default daily run quota from governance settings.
     *
     * @throws RuntimeRequestException
     */
    private function assertDefaultQuota(Team $team, Environment $environment): void
    {
        $default = GovernanceSetting::forTeam($team)->dailyRunQuota($environment);

        if ($default === null) {
            return;
        }

        $runs = $this->scopedRuns($team)->where('environment', $environment->value)->count();

        if ($runs >= $default) {
            throw RuntimeRequestException::quotaExceeded('default daily run quota');
        }
    }

    /**
     * Determine whether the quota's scope matches the run's dimension.
     */
    private function matches(QuotaLimit $quota, Application $application, Agent $agent): bool
    {
        return match ($quota->scope) {
            QuotaScope::Platform => true,
            QuotaScope::Application => $quota->subject_id === $application->id,
            QuotaScope::Project => $quota->subject_id === $agent->project_id,
            QuotaScope::Agent => $quota->subject_id === $agent->id,
            QuotaScope::Model => $quota->subject_id === $agent->llm_provider_id,
        };
    }

    /**
     * Enforce a single quota's run and token caps.
     *
     * @throws RuntimeRequestException
     */
    private function enforce(QuotaLimit $quota, Application $application, Agent $agent): void
    {
        if ($quota->max_runs_per_day !== null
            && $this->usage($quota, $application, $agent)->count() >= $quota->max_runs_per_day) {
            throw RuntimeRequestException::quotaExceeded($quota->scope->label().' daily run quota');
        }

        if ($quota->max_tokens_per_day !== null) {
            $tokens = (int) $this->usage($quota, $application, $agent)->sum('tokens_in')
                + (int) $this->usage($quota, $application, $agent)->sum('tokens_out');

            if ($tokens >= $quota->max_tokens_per_day) {
                throw RuntimeRequestException::quotaExceeded($quota->scope->label().' daily token quota');
            }
        }
    }

    /**
     * Build today's usage query for the quota's scope and environment.
     *
     * @return Builder<AgentRun>
     */
    private function usage(QuotaLimit $quota, Application $application, Agent $agent): Builder
    {
        $query = $this->scopedRuns($application->team);

        match ($quota->scope) {
            QuotaScope::Platform => null,
            QuotaScope::Application => $query->where('application_id', $application->id),
            QuotaScope::Project => $query->where('project_id', $agent->project_id),
            QuotaScope::Agent => $query->where('agent_id', $agent->id),
            QuotaScope::Model => $query->where('llm_provider_id', $agent->llm_provider_id),
        };

        if ($quota->environment !== null) {
            $query->where('environment', $quota->environment->value);
        }

        return $query;
    }

    /**
     * Base query for today's runs owned by the team.
     *
     * @return Builder<AgentRun>
     */
    private function scopedRuns(Team $team): Builder
    {
        return AgentRun::query()
            ->whereHas('application', fn (Builder $query) => $query->where('team_id', $team->id))
            ->where('started_at', '>=', Date::now()->startOfDay());
    }
}
