<?php

namespace App\Support\Observability;

use App\Enums\RunStatus;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Project;
use App\Models\Team;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Number;

/**
 * Computes the run/observability rollups that back the MAAC dashboard from real
 * Agent Run records: today's volume, status distribution, hourly trend, token
 * and cost totals, and the most-used agents. Replaces the Phase 1 fixture
 * `dashboard` block with truthful aggregates.
 */
class RunMetrics
{
    /**
     * The display order and color for each run status in the distribution chart.
     */
    private const STATUS_COLORS = [
        'completed' => 'var(--teal-500)',
        'waiting_for_client' => 'var(--orange-600)',
        'running' => 'var(--blue-500)',
        'failed' => 'var(--red-500)',
        'expired' => 'var(--amber-500)',
        'cancelled' => 'var(--text-3)',
    ];

    /**
     * Build the dashboard metric rollups for the given team.
     *
     * @return array{
     *     stats: array<string, mixed>,
     *     runStatus: array<int, array{label: string, value: int, color: string}>,
     *     runsOverTime: array<int, int>,
     *     topAgents: array<int, array{id: string, name: string, runs: int, app: string|null}>,
     * }
     */
    public function forTeam(Team $team): array
    {
        $today = $this->todayRuns($team);

        return [
            'stats' => $this->stats($team, $today),
            'runStatus' => $this->runStatus($today),
            'runsOverTime' => $this->runsOverTime($team),
            'topAgents' => $this->topAgents($team),
        ];
    }

    /**
     * Get today's runs for the team (only the columns the rollups need).
     *
     * @return Collection<int, AgentRun>
     */
    private function todayRuns(Team $team): Collection
    {
        return $this->scopedRuns($team)
            ->where('started_at', '>=', Date::now()->startOfDay())
            ->get(['id', 'status', 'tokens_in', 'tokens_out', 'cost', 'started_at']);
    }

    /**
     * Build the headline stat tiles.
     *
     * @param  Collection<int, AgentRun>  $today
     * @return array<string, mixed>
     */
    private function stats(Team $team, Collection $today): array
    {
        $tokens = (int) $today->sum(fn (AgentRun $run): int => $run->tokens_in + $run->tokens_out);
        $cost = (float) $today->sum('cost');

        return [
            'apps' => $team->applications()->count(),
            'projects' => $this->scopedProjects($team)->count(),
            'agents' => $this->scopedAgents($team)->count(),
            'tools' => $team->toolContracts()->count(),
            'runsToday' => $today->count(),
            'waitingClient' => $today->where('status', RunStatus::WaitingForClient)->count(),
            'success' => $today->where('status', RunStatus::Completed)->count(),
            'failed' => $today->where('status', RunStatus::Failed)->count(),
            'tokens' => Number::abbreviate($tokens, maxPrecision: 2),
            'cost' => 'QAR '.Number::format($cost, maxPrecision: 2),
        ];
    }

    /**
     * Build the run status distribution for the donut chart.
     *
     * @param  Collection<int, AgentRun>  $today
     * @return array<int, array{label: string, value: int, color: string}>
     */
    private function runStatus(Collection $today): array
    {
        $rows = [];

        foreach (self::STATUS_COLORS as $value => $color) {
            $status = RunStatus::from($value);

            $rows[] = [
                'label' => $status->label(),
                'value' => $today->where('status', $status)->count(),
                'color' => $color,
            ];
        }

        return $rows;
    }

    /**
     * Build a 24-bucket hourly run-volume series for the last 24 hours.
     *
     * @return array<int, int>
     */
    private function runsOverTime(Team $team): array
    {
        $since = Date::now()->subHours(23)->startOfHour();
        $buckets = array_fill(0, 24, 0);

        $this->scopedRuns($team)
            ->where('started_at', '>=', $since)
            ->pluck('started_at')
            ->each(function (CarbonInterface $startedAt) use ($since, &$buckets): void {
                $index = (int) $since->diffInHours($startedAt);

                if ($index < 24) {
                    $buckets[$index]++;
                }
            });

        return $buckets;
    }

    /**
     * Build the most-used agents list (by lifetime run count).
     *
     * @return array<int, array{id: string, name: string, runs: int, app: string|null}>
     */
    private function topAgents(Team $team): array
    {
        return $this->scopedAgents($team)
            ->with('project.application')
            ->withCount('runs')
            ->orderByDesc('runs_count')
            ->limit(5)
            ->get()
            ->map(fn (Agent $agent): array => [
                'id' => $agent->slug,
                'name' => $agent->name,
                'runs' => (int) $agent->runs_count,
                'app' => $agent->project->application->slug,
            ])
            ->all();
    }

    /**
     * Base query for runs owned by the team.
     *
     * @return Builder<AgentRun>
     */
    private function scopedRuns(Team $team): Builder
    {
        return AgentRun::query()
            ->whereHas('application', fn (Builder $query) => $query->where('team_id', $team->id));
    }

    /**
     * Base query for agents owned by the team.
     *
     * @return Builder<Agent>
     */
    private function scopedAgents(Team $team): Builder
    {
        return Agent::query()
            ->whereHas('project.application', fn (Builder $query) => $query->where('team_id', $team->id));
    }

    /**
     * Base query for projects owned by the team.
     *
     * @return Builder<Project>
     */
    private function scopedProjects(Team $team): Builder
    {
        return Project::query()
            ->whereHas('application', fn (Builder $query) => $query->where('team_id', $team->id));
    }
}
