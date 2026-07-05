<?php

namespace App\Support\Runtime\Routing;

use App\Enums\Environment;
use App\Models\AgentRun;
use Illuminate\Support\Facades\Date;

/**
 * Computes recent-health snapshots for model providers from the run history. A
 * provider is unhealthy when its model-attributable failure rate over the recent
 * window exceeds the configured threshold (and a minimum sample has been seen).
 * Used by the {@see ModelRouter} to rank candidates and to fail over from a
 * degraded provider.
 */
class ProviderHealth
{
    /**
     * The failure reasons attributable to the model provider (rather than the
     * caller, tool, or policy), which drive the health signal.
     *
     * @var array<int, string>
     */
    private const MODEL_FAILURES = ['model_error', 'model_unavailable'];

    /**
     * Build a health snapshot for each given provider id, keyed by id. Providers
     * with no recent runs get a neutral, healthy snapshot.
     *
     * @param  array<int, string>  $providerIds
     * @return array<string, ProviderHealthSnapshot>
     */
    public function forProviderIds(array $providerIds, ?Environment $environment = null): array
    {
        if ($providerIds === []) {
            return [];
        }

        $since = Date::now()->subMinutes((int) config('maacc.routing.health_window_minutes'));

        $rows = AgentRun::query()
            ->selectRaw('llm_provider_id')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when failure_reason in (?, ?) then 1 else 0 end) as failures', self::MODEL_FAILURES)
            ->selectRaw('avg(latency_ms) as avg_latency')
            ->whereIn('llm_provider_id', $providerIds)
            ->where('started_at', '>=', $since)
            ->when($environment !== null, fn ($query) => $query->where('environment', $environment))
            ->groupBy('llm_provider_id')
            ->get()
            ->keyBy('llm_provider_id');

        $snapshots = [];

        foreach ($providerIds as $id) {
            $row = $rows->get($id);
            $snapshots[$id] = $row === null
                ? ProviderHealthSnapshot::unknown()
                : $this->snapshot((int) $row->getAttribute('total'), (int) $row->getAttribute('failures'), $row->getAttribute('avg_latency'));
        }

        return $snapshots;
    }

    /**
     * Build a single provider's snapshot from its aggregated run counts.
     */
    private function snapshot(int $total, int $failures, mixed $avgLatency): ProviderHealthSnapshot
    {
        $failureRate = $total > 0 ? $failures / $total : 0.0;
        $minSample = (int) config('maacc.routing.health_min_sample');
        $threshold = (float) config('maacc.routing.health_failure_threshold');

        $healthy = $total < $minSample || $failureRate <= $threshold;

        return new ProviderHealthSnapshot(
            $total,
            round($failureRate, 4),
            $healthy,
            $avgLatency === null ? null : (int) round((float) $avgLatency),
        );
    }
}
