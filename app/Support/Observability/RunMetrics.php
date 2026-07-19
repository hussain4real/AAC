<?php

namespace App\Support\Observability;

use App\Enums\RunStatus;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\Project;
use App\Models\Team;
use App\Models\ToolContract;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Number;

/**
 * Computes the run/observability rollups that back the MAACC dashboard from real
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
     * @param  array<int, string>|null  $projectIds
     * @return array{
     *     stats: array<string, mixed>,
     *     runStatus: array<int, array{label: string, value: int, color: string}>,
     *     runsOverTime: array<int, int>,
     *     topAgents: array<int, array{id: string, name: string, runs: int, app: string|null}>,
     *     usageByUser: array<int, array{key: string, runs: int, tokens: int, cost: float}>,
     *     usageByDepartment: array<int, array{key: string, runs: int, tokens: int, cost: float}>,
     *     reporting: array<string, mixed>,
     * }
     */
    public function forTeam(Team $team, ?array $projectIds = null): array
    {
        $measuredAt = Date::now();
        $today = $this->todayRuns($team, $measuredAt, $projectIds);

        return [
            'stats' => $this->stats($team, $today, $projectIds),
            'runStatus' => $this->runStatus($today),
            'runsOverTime' => $this->runsOverTime($team, $measuredAt, $projectIds),
            'topAgents' => $this->topAgents($team, $projectIds),
            'usageByUser' => $this->usageBreakdown($team, 'caller_subject', $measuredAt, $projectIds),
            'usageByDepartment' => $this->usageBreakdown($team, 'caller_department', $measuredAt, $projectIds),
            'reporting' => [
                'source' => 'agent_runs',
                'measuredAt' => $measuredAt->toIso8601String(),
                'timezone' => config('app.timezone'),
                'window' => 'today and trailing 24 hours; caller breakdown trailing 7 days',
                'cost' => [
                    'estimated' => true,
                    'currency' => config('maacc.pricing.currency', 'USD'),
                    'unit' => config('maacc.pricing.unit', 'per_million_tokens'),
                    'source' => config('maacc.pricing.source'),
                    'version' => config('maacc.pricing.version'),
                    'effectiveAt' => config('maacc.pricing.effective_at'),
                ],
            ],
        ];
    }

    /**
     * Get today's runs for the team (only the columns the rollups need).
     *
     * @param  array<int, string>|null  $projectIds
     * @return Collection<int, AgentRun>
     */
    private function todayRuns(Team $team, CarbonInterface $measuredAt, ?array $projectIds): Collection
    {
        return $this->scopedRuns($team, $projectIds)
            ->where('started_at', '>=', $measuredAt->toImmutable()->startOfDay())
            ->get(['id', 'status', 'tokens_in', 'tokens_out', 'cost', 'cost_currency', 'started_at']);
    }

    /**
     * Build the headline stat tiles.
     *
     * @param  Collection<int, AgentRun>  $today
     * @param  array<int, string>|null  $projectIds
     * @return array<string, mixed>
     */
    private function stats(Team $team, Collection $today, ?array $projectIds): array
    {
        $tokens = (int) $today->sum(fn (AgentRun $run): int => $run->tokens_in + $run->tokens_out);
        $cost = (float) $today->sum('cost');
        $currencies = $today->pluck('cost_currency')->filter()->unique();
        $currency = $currencies->count() === 1 ? $currencies->first() : (string) config('maacc.pricing.currency', 'USD');

        return [
            'apps' => $this->scopedApplications($team, $projectIds)->count(),
            'projects' => $this->scopedProjects($team, $projectIds)->count(),
            'agents' => $this->scopedAgents($team, $projectIds)->count(),
            'tools' => $this->scopedTools($team, $projectIds)->count(),
            'runsToday' => $today->count(),
            'waitingClient' => $today->where('status', RunStatus::WaitingForClient)->count(),
            'success' => $today->where('status', RunStatus::Completed)->count(),
            'failed' => $today->where('status', RunStatus::Failed)->count(),
            'tokens' => Number::abbreviate($tokens, maxPrecision: 2),
            'cost' => $currency.' '.Number::format($cost, maxPrecision: 2),
            'costEstimated' => true,
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
     * @param  array<int, string>|null  $projectIds
     * @return array<int, int>
     */
    private function runsOverTime(Team $team, CarbonInterface $measuredAt, ?array $projectIds): array
    {
        $since = $measuredAt->toImmutable()->subHours(23)->startOfHour();
        $buckets = array_fill(0, 24, 0);

        $this->scopedRuns($team, $projectIds)
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
     * Aggregate trusted, normalized caller context without exposing raw PII.
     *
     * @param  array<int, string>|null  $projectIds
     * @return array<int, array{key: string, runs: int, tokens: int, cost: float}>
     */
    private function usageBreakdown(Team $team, string $column, CarbonInterface $measuredAt, ?array $projectIds = null): array
    {
        $selection = match ($column) {
            'caller_subject' => 'caller_subject as key, count(*) as runs, sum(tokens_in + tokens_out) as tokens, sum(cost) as cost',
            'caller_department' => 'caller_department as key, count(*) as runs, sum(tokens_in + tokens_out) as tokens, sum(cost) as cost',
            default => throw new \InvalidArgumentException('Unsupported caller reporting dimension.'),
        };

        /** @var SupportCollection<int, object{key: string, runs: int, tokens: int, cost: float}> $rows */
        $rows = $this->scopedRuns($team, $projectIds)
            ->where('created_at', '>=', $measuredAt->toImmutable()->subDays(7))
            ->whereNotNull($column)
            ->selectRaw($selection)
            ->groupBy($column)
            ->orderByDesc('runs')
            ->limit(10)
            ->get();

        return $rows->map(fn (object $row): array => [
            'key' => hash('sha256', $team->id.'|'.$column.'|'.$row->key),
            'runs' => (int) $row->runs,
            'tokens' => (int) $row->tokens,
            'cost' => round((float) $row->cost, 6),
        ])->all();
    }

    /**
     * Build the most-used agents list (by lifetime run count).
     *
     * @param  array<int, string>|null  $projectIds
     * @return array<int, array{id: string, name: string, runs: int, app: string|null}>
     */
    private function topAgents(Team $team, ?array $projectIds): array
    {
        return $this->scopedAgents($team, $projectIds)
            ->with('project.application')
            ->withCount(['runs' => function (Builder $query) use ($projectIds): void {
                if ($projectIds !== null) {
                    $query->whereIn('project_id', $projectIds);
                }
            }])
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
     * @param  array<int, string>|null  $projectIds
     * @return Builder<AgentRun>
     */
    private function scopedRuns(Team $team, ?array $projectIds): Builder
    {
        $query = AgentRun::query()
            ->whereHas('application', fn (Builder $query) => $query->where('team_id', $team->id));

        return $projectIds === null ? $query : $query->whereIn('project_id', $projectIds);
    }

    /**
     * Base query for agents owned by the team.
     *
     * @param  array<int, string>|null  $projectIds
     * @return Builder<Agent>
     */
    private function scopedAgents(Team $team, ?array $projectIds): Builder
    {
        $query = Agent::query()
            ->whereHas('project.application', fn (Builder $query) => $query->where('team_id', $team->id));

        return $projectIds === null ? $query : $query->whereIn('project_id', $projectIds);
    }

    /**
     * Base query for projects owned by the team.
     *
     * @param  array<int, string>|null  $projectIds
     * @return Builder<Project>
     */
    private function scopedProjects(Team $team, ?array $projectIds): Builder
    {
        $query = Project::query()
            ->whereHas('application', fn (Builder $query) => $query->where('team_id', $team->id));

        return $projectIds === null ? $query : $query->whereIn('id', $projectIds);
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return Builder<Application>
     */
    private function scopedApplications(Team $team, ?array $projectIds): Builder
    {
        $query = Application::query()->where('team_id', $team->id);

        if ($projectIds !== null) {
            $query->whereHas('projects', fn (Builder $builder) => $builder->whereIn('id', $projectIds));
        }

        return $query;
    }

    /**
     * @param  array<int, string>|null  $projectIds
     * @return Builder<ToolContract>
     */
    private function scopedTools(Team $team, ?array $projectIds): Builder
    {
        $query = ToolContract::query()->where('team_id', $team->id);

        if ($projectIds !== null) {
            $query->where(function (Builder $builder) use ($projectIds): void {
                $builder->whereHas('assignments', fn (Builder $assignment) => $assignment->whereIn('tool_assignments.project_id', $projectIds))
                    ->orWhereHas('agents', fn (Builder $agent) => $agent->whereIn('agents.project_id', $projectIds));
            });
        }

        return $query;
    }
}
