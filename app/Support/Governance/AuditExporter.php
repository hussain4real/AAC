<?php

namespace App\Support\Governance;

use App\Models\AuditEvent;
use App\Models\GovernanceSetting;
use App\Models\Team;
use Illuminate\Support\Facades\Date;

/**
 * Builds an enterprise audit export for security review: a filtered slice of a
 * team's audit log plus an independently keyed HMAC manifest. The unkeyed row
 * digest supports transport diagnostics; authenticity comes from the signature.
 */
class AuditExporter
{
    public function __construct(
        private readonly AuditSigner $signer,
        private readonly AuditIntegrityVerifier $verifier,
    ) {}

    /**
     * The hard cap on exported rows; the manifest flags when it truncates.
     */
    private const MAX_ROWS = 10000;

    /**
     * Build the export rows and signed manifest for the given filters.
     *
     * @param  array{from?: ?string, to?: ?string, action?: ?string, actor?: ?int}  $filters
     * @return array{rows: array<int, array<string, mixed>>, manifest: array<string, mixed>}
     */
    public function export(Team $team, array $filters): array
    {
        $query = $team->auditEvents()->with('actor')->latest();

        if (! empty($filters['from'])) {
            $query->where('created_at', '>=', $filters['from']);
        }

        if (! empty($filters['to'])) {
            $query->where('created_at', '<=', $filters['to']);
        }

        if (! empty($filters['action'])) {
            $query->where('action', 'like', $filters['action'].'%');
        }

        if (! empty($filters['actor'])) {
            $query->where('actor_user_id', $filters['actor']);
        }

        $total = (clone $query)->count();
        $rows = $query->limit(self::MAX_ROWS)->get()
            ->map(fn (AuditEvent $event): array => $this->row($event))
            ->all();

        $manifest = [
            'generated_at' => Date::now()->toIso8601String(),
            'team' => $team->slug,
            'count' => count($rows),
            'total_matched' => $total,
            'truncated' => $total > count($rows),
            'filters' => array_filter($filters, fn (mixed $value): bool => $value !== null && $value !== ''),
            'audit_retention_days' => GovernanceSetting::forTeam($team)->retentionDaysFor('audit'),
            'rows_digest' => hash('sha256', (string) json_encode($rows)),
            'chain_integrity' => $this->verifier->verify($team),
            'signature_key_id' => $this->signer->keyId('export'),
        ];
        $manifest['signature'] = $this->signer->signExport($manifest);

        return ['rows' => $rows, 'manifest' => $manifest];
    }

    /**
     * Render an export as a pretty JSON document (manifest + events).
     *
     * @param  array{rows: array<int, array<string, mixed>>, manifest: array<string, mixed>}  $export
     */
    public function json(array $export): string
    {
        return (string) json_encode([
            'manifest' => $export['manifest'],
            'events' => $export['rows'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Render an export as an RFC 4180 CSV document (the signed manifest rides in
     * response headers).
     *
     * @param  array{rows: array<int, array<string, mixed>>, manifest: array<string, mixed>}  $export
     */
    public function csv(array $export): string
    {
        $columns = ['id', 'action', 'actor', 'target_type', 'target_id', 'environment', 'ip_address', 'occurred_at', 'metadata'];
        $lines = [$this->csvRow($columns)];

        foreach ($export['rows'] as $row) {
            $lines[] = $this->csvRow(array_map(fn (string $column): string => (string) ($row[$column] ?? ''), $columns));
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Render a single CSV row, quoting any cell that needs it.
     *
     * @param  array<int, string>  $cells
     */
    private function csvRow(array $cells): string
    {
        return implode(',', array_map(
            fn (string $cell): string => preg_match('/[",\r\n]/', $cell) === 1
                ? '"'.str_replace('"', '""', $cell).'"'
                : $cell,
            $cells,
        ));
    }

    /**
     * Flatten one audit event into an export row.
     *
     * @return array<string, mixed>
     */
    private function row(AuditEvent $event): array
    {
        return [
            'id' => $event->id,
            'action' => $event->action,
            'actor' => $event->actor_label ?? 'System',
            'actor_id' => $event->actor_user_id,
            'target_type' => $event->auditable_type !== null ? class_basename($event->auditable_type) : null,
            'target_id' => $event->auditable_id,
            'environment' => $event->environment?->value,
            'ip_address' => $event->ip_address,
            'occurred_at' => $event->created_at?->toIso8601String(),
            'metadata' => $event->metadata !== null ? (string) json_encode($event->metadata) : null,
        ];
    }
}
