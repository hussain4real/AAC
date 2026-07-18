<?php

namespace App\Support\Observability;

use App\Enums\SsoFailureCode;
use App\Models\AuditEvent;
use App\Models\SsoConnection;
use App\Support\Governance\AuditLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SsoAnomalyDetector
{
    /**
     * Persist one deduplicated anomaly signal when a high-risk failure occurs or
     * a connection crosses the configured rejected-login threshold.
     */
    public function detect(SsoConnection $connection, Request $request, SsoFailureCode $code, string $correlationId): void
    {
        if ($code->category() !== 'security') {
            return;
        }

        $windowMinutes = max(1, (int) config('maacc.sso.alert_window_minutes', 15));
        $threshold = max(1, (int) config('maacc.sso.rejected_login_alert_threshold', 5));
        $recentFailures = AuditEvent::query()
            ->where('team_id', $connection->team_id)
            ->where('action', 'sso.login_rejected')
            ->where('auditable_type', $connection->getMorphClass())
            ->where('auditable_id', $connection->id)
            ->where('created_at', '>=', now()->subMinutes($windowMinutes))
            ->count();

        if (! $code->triggersImmediateAnomaly() && $recentFailures < $threshold) {
            return;
        }

        $deduplicationKey = 'maacc:sso:anomaly:'.$connection->team_id.':'.$connection->id;

        if (! Cache::add($deduplicationKey, true, now()->addMinutes($windowMinutes))) {
            return;
        }

        app(AuditLedger::class)->record([
            'team_id' => $connection->team_id,
            'actor_label' => 'system',
            'action' => 'sso.anomaly.detected',
            'auditable_type' => $connection->getMorphClass(),
            'auditable_id' => $connection->id,
            'metadata' => [
                'connection' => $connection->slug,
                'failure_code' => $code->value,
                'severity' => $code->severity()->value,
                'correlation_id' => $correlationId,
                'recent_failures' => $recentFailures,
                'window_minutes' => $windowMinutes,
            ],
            'ip_address' => $request->ip(),
        ]);

        Log::critical('SSO anomaly detected', [
            'connection_id' => $connection->id,
            'team_id' => $connection->team_id,
            'failure_code' => $code->value,
            'correlation_id' => $correlationId,
            'recent_failures' => $recentFailures,
        ]);
    }
}
