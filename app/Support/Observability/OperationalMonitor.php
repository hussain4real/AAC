<?php

namespace App\Support\Observability;

use App\Enums\AlertSeverity;
use App\Enums\CredentialStatus;
use App\Enums\RunStatus;
use App\Enums\ToolCallStatus;
use App\Models\AgentRun;
use App\Models\Credential;
use App\Models\Team;
use App\Models\ToolCall;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

/**
 * Derives operational monitoring signals (error rate, waiting/expired runs,
 * average latency, tool failure rate, cost anomalies) and the security &
 * governance alert feed from real run, tool-call, credential, and approval
 * records for a team.
 */
class OperationalMonitor
{
    /**
     * The trailing window (days) operational metrics are computed over.
     */
    private const WINDOW_DAYS = 7;

    /**
     * Build the operational metrics and alert feed for the given team.
     *
     * @return array{metrics: array<string, mixed>, alerts: array<int, array<string, mixed>>}
     */
    public function forTeam(Team $team): array
    {
        $window = Date::now()->subDays(self::WINDOW_DAYS);
        $metrics = $this->metrics($team, $window);

        return [
            'metrics' => $metrics,
            'alerts' => $this->alerts($team, $window, $metrics),
        ];
    }

    /**
     * Compute the operational metric summary.
     *
     * @return array<string, mixed>
     */
    private function metrics(Team $team, CarbonInterface $window): array
    {
        $total = $this->runsSince($team, $window)->count();
        $failed = $this->runsSince($team, $window)->where('status', RunStatus::Failed)->count();
        $expired = $this->runsSince($team, $window)->where('status', RunStatus::Expired)->count();
        $waiting = $this->scopedRuns($team)->where('status', RunStatus::WaitingForClient)->count();
        $avgLatency = (int) round((float) $this->runsSince($team, $window)
            ->where('status', RunStatus::Completed)
            ->avg('latency_ms'));

        $toolTotal = $this->toolCallsSince($team, $window)->count();
        $toolFailed = $this->toolCallsSince($team, $window)->where('status', ToolCallStatus::Failed)->count();

        return [
            'totalRuns' => $total,
            'failedRuns' => $failed,
            'expiredRuns' => $expired,
            'waitingRuns' => $waiting,
            'avgLatencyMs' => $avgLatency,
            'errorRate' => $this->rate($failed, $total),
            'toolFailureRate' => $this->rate($toolFailed, $toolTotal),
            'costAnomaly' => $this->detectCostAnomaly($team),
        ];
    }

    /**
     * Build the security & governance alert feed, most severe first.
     *
     * @param  array<string, mixed>  $metrics
     * @return array<int, array<string, mixed>>
     */
    private function alerts(Team $team, CarbonInterface $window, array $metrics): array
    {
        $alerts = [];

        if ($metrics['failedRuns'] > 0) {
            $latest = $this->runsSince($team, $window)->where('status', RunStatus::Failed)->latest('started_at')->first();
            $alerts[] = $this->alert(
                AlertSeverity::High,
                'shield-alert',
                $metrics['failedRuns'].' '.Str::plural('run', $metrics['failedRuns']).' failed',
                $latest->error ?? 'One or more runs failed during execution.',
                $latest->started_at,
            );
        }

        if ($metrics['waitingRuns'] > 0) {
            $latest = $this->scopedRuns($team)->where('status', RunStatus::WaitingForClient)->latest('started_at')->first();
            $alerts[] = $this->alert(
                AlertSeverity::Medium,
                'clock',
                $metrics['waitingRuns'].' '.Str::plural('run', $metrics['waitingRuns']).' waiting for client tools',
                'Client-side tool results are pending SDK execution.',
                $latest?->started_at,
            );
        }

        if ($metrics['expiredRuns'] > 0) {
            $latest = $this->runsSince($team, $window)->where('status', RunStatus::Expired)->latest('started_at')->first();
            $alerts[] = $this->alert(
                AlertSeverity::Medium,
                'clock',
                $metrics['expiredRuns'].' '.Str::plural('run', $metrics['expiredRuns']).' expired',
                'Runs exceeded their execution timeout before completing.',
                $latest?->started_at,
            );
        }

        $revoked = Credential::query()
            ->whereHas('application', fn (Builder $query) => $query->where('team_id', $team->id))
            ->where('status', CredentialStatus::Revoked)
            ->where('revoked_at', '>=', $window)
            ->latest('revoked_at')
            ->first();

        if ($revoked !== null) {
            $alerts[] = $this->alert(
                AlertSeverity::Medium,
                'key',
                'Credential revoked',
                'An application credential was revoked.',
                $revoked->revoked_at,
            );
        }

        $pending = $team->approvalRequests()->pending()->count();

        if ($pending > 0) {
            $latest = $team->approvalRequests()->pending()->latest()->first();
            $alerts[] = $this->alert(
                AlertSeverity::Low,
                'check2',
                $pending.' '.Str::plural('change', $pending).' awaiting approval',
                'Governance approvals are pending review.',
                $latest?->created_at,
            );
        }

        if ($metrics['costAnomaly']) {
            $alerts[] = $this->alert(
                AlertSeverity::High,
                'bolt',
                'Cost anomaly detected',
                "Today's estimated cost is significantly above the 7-day average.",
                Date::now(),
            );
        }

        usort($alerts, fn (array $a, array $b): int => $b['weight'] <=> $a['weight']);

        return array_map(fn (array $alert): array => Arr::except($alert, 'weight'), $alerts);
    }

    /**
     * Build a single alert entry (the console contract shape plus a sort weight).
     *
     * @return array<string, mixed>
     */
    private function alert(AlertSeverity $severity, string $icon, string $title, string $desc, ?CarbonInterface $at): array
    {
        return [
            'sev' => $severity->value,
            'weight' => $severity->weight(),
            'title' => $title,
            'desc' => $desc,
            'time' => $at?->diffForHumans() ?? 'just now',
            'icon' => $icon,
        ];
    }

    /**
     * Detect a cost anomaly: today's spend significantly above the trailing
     * daily average.
     */
    private function detectCostAnomaly(Team $team): bool
    {
        $startOfToday = Date::now()->startOfDay();
        $todayCost = (float) $this->scopedRuns($team)->where('started_at', '>=', $startOfToday)->sum('cost');
        $priorCost = (float) $this->scopedRuns($team)
            ->whereBetween('started_at', [$startOfToday->copy()->subDays(self::WINDOW_DAYS), $startOfToday])
            ->sum('cost');
        $averageDaily = $priorCost / self::WINDOW_DAYS;

        return $averageDaily > 0 && $todayCost > $averageDaily * 1.5;
    }

    /**
     * Compute a percentage rate, rounded to one decimal place.
     */
    private function rate(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round($numerator / $denominator * 100, 1) : 0.0;
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
     * Query for the team's runs within the trailing window.
     *
     * @return Builder<AgentRun>
     */
    private function runsSince(Team $team, CarbonInterface $window): Builder
    {
        return $this->scopedRuns($team)->where('started_at', '>=', $window);
    }

    /**
     * Query for the team's tool calls within the trailing window.
     *
     * @return Builder<ToolCall>
     */
    private function toolCallsSince(Team $team, CarbonInterface $window): Builder
    {
        return ToolCall::query()
            ->whereHas('agentRun.application', fn (Builder $query) => $query->where('team_id', $team->id))
            ->where('requested_at', '>=', $window);
    }
}
