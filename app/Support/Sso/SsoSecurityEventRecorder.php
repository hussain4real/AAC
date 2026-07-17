<?php

namespace App\Support\Sso;

use App\Enums\SsoFailureCode;
use App\Models\AuditEvent;
use App\Models\SsoConnection;
use App\Support\Observability\SsoAnomalyDetector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SsoSecurityEventRecorder
{
    public function __construct(private readonly SsoAnomalyDetector $anomalies) {}

    /**
     * Record a sanitized, correlated SSO failure and return its reference ID.
     */
    public function record(SsoConnection $connection, Request $request, SsoFailureCode $code): string
    {
        $correlationId = $request->attributes->get('correlation_id');
        $correlationId = is_string($correlationId) && $correlationId !== ''
            ? $correlationId
            : 'corr_'.Str::lower((string) Str::ulid());
        $request->attributes->set('correlation_id', $correlationId);

        AuditEvent::create([
            'team_id' => $connection->team_id,
            'actor_label' => 'anonymous',
            'action' => $code->auditAction(),
            'auditable_type' => $connection->getMorphClass(),
            'auditable_id' => $connection->id,
            'metadata' => [
                'connection' => $connection->slug,
                'failure_code' => $code->value,
                'category' => $code->category(),
                'severity' => $code->severity()->value,
                'correlation_id' => $correlationId,
            ],
            'ip_address' => $request->ip(),
        ]);

        Log::warning('SSO login rejected', [
            'connection_id' => $connection->id,
            'team_id' => $connection->team_id,
            'failure_code' => $code->value,
            'category' => $code->category(),
            'severity' => $code->severity()->value,
            'correlation_id' => $correlationId,
            'ip' => $request->ip(),
        ]);

        $this->anomalies->detect($connection, $request, $code, $correlationId);

        return $correlationId;
    }
}
