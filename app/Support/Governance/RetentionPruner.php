<?php

namespace App\Support\Governance;

use App\Enums\Environment;
use App\Enums\RunStatus;
use App\Models\AgentRun;
use App\Models\GovernanceSetting;
use App\Models\Team;
use App\Models\ToolCall;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;

/**
 * Enforces configurable retention policies by redacting stored run payloads
 * (prompts, responses, tool arguments, tool results) and deleting audit events
 * once they pass their team's retention window. Retention windows can be
 * separated per environment via governance setting overrides.
 */
class RetentionPruner
{
    /**
     * Prune every team and return the aggregate counts.
     *
     * @return array{runs: int, audits: int}
     */
    public function prune(): array
    {
        $runs = 0;
        $audits = 0;

        Team::query()->each(function (Team $team) use (&$runs, &$audits): void {
            $result = $this->pruneTeam($team);
            $runs += $result['runs'];
            $audits += $result['audits'];
        });

        return ['runs' => $runs, 'audits' => $audits];
    }

    /**
     * Prune a single team's run payloads and audit events.
     *
     * @return array{runs: int, audits: int}
     */
    public function pruneTeam(Team $team): array
    {
        $settings = GovernanceSetting::forTeam($team);
        $runs = 0;

        foreach (Environment::cases() as $environment) {
            $runs += $this->pruneRunsForEnvironment($team, $settings, $environment);
        }

        return ['runs' => $runs, 'audits' => $this->pruneAudit($team, $settings)];
    }

    /**
     * Redact terminal-run payloads past their retention windows in one
     * environment, returning the number of fields cleared.
     */
    private function pruneRunsForEnvironment(Team $team, GovernanceSetting $settings, Environment $environment): int
    {
        $terminal = $this->terminalStatuses();
        $affected = 0;

        $affected += $this->scopedRuns($team, $environment, $terminal)
            ->where('completed_at', '<', Date::now()->subDays($settings->retentionDaysFor('prompts', $environment)))
            ->whereNotNull('input')
            ->update(['input' => null, 'state' => null]);

        $affected += $this->scopedRuns($team, $environment, $terminal)
            ->where('completed_at', '<', Date::now()->subDays($settings->retentionDaysFor('responses', $environment)))
            ->whereNotNull('output')
            ->update(['output' => null]);

        $affected += $this->scopedToolCalls($team, $environment)
            ->where('completed_at', '<', Date::now()->subDays($settings->retentionDaysFor('tool_arguments', $environment)))
            ->whereNotNull('arguments')
            ->update(['arguments' => null]);

        $affected += $this->scopedToolCalls($team, $environment)
            ->where('completed_at', '<', Date::now()->subDays($settings->retentionDaysFor('tool_results', $environment)))
            ->whereNotNull('result')
            ->update(['result' => null]);

        return $affected;
    }

    /**
     * Delete audit events older than the team's audit retention window.
     */
    private function pruneAudit(Team $team, GovernanceSetting $settings): int
    {
        return $team->auditEvents()
            ->where('created_at', '<', Date::now()->subDays($settings->retentionDaysFor('audit')))
            ->delete();
    }

    /**
     * Build a fresh query for the team's terminal runs in an environment.
     *
     * @param  array<int, string>  $terminal
     * @return Builder<AgentRun>
     */
    private function scopedRuns(Team $team, Environment $environment, array $terminal): Builder
    {
        return AgentRun::query()
            ->whereHas('application', fn (Builder $query) => $query->where('team_id', $team->id))
            ->where('environment', $environment->value)
            ->whereIn('status', $terminal);
    }

    /**
     * Build a fresh query for the team's tool calls in an environment.
     *
     * @return Builder<ToolCall>
     */
    private function scopedToolCalls(Team $team, Environment $environment): Builder
    {
        return ToolCall::query()
            ->whereHas('agentRun', fn (Builder $query) => $query
                ->whereHas('application', fn (Builder $inner) => $inner->where('team_id', $team->id))
                ->where('environment', $environment->value));
    }

    /**
     * The terminal run status raw values.
     *
     * @return array<int, string>
     */
    private function terminalStatuses(): array
    {
        return array_values(array_map(
            fn (RunStatus $status): string => $status->value,
            array_filter(RunStatus::cases(), fn (RunStatus $status): bool => $status->isTerminal()),
        ));
    }
}
