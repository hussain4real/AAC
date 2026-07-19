<?php

namespace App\Support\Sdk;

use App\Enums\ExecMode;
use App\Models\Team;
use App\Models\ToolContractVersion;
use App\Models\ToolImplementationEvent;
use App\Support\Governance\AuditExporter;
use App\Support\Governance\AuditSigner;
use Illuminate\Support\Facades\Date;

/**
 * Builds a downloadable export of a team's tool version journey: the flat
 * implementation event timeline and the contract version snapshots for its
 * client-side tools, plus a signed manifest (generated time, counts, truncation,
 * and an independently keyed HMAC signature) so authenticity can be verified. Serializes
 * to JSON or CSV, mirroring {@see AuditExporter}.
 */
class VersionJourneyExporter
{
    public function __construct(private readonly AuditSigner $signer) {}

    /**
     * The hard cap on exported rows per history; the manifest flags truncation.
     */
    private const MAX_ROWS = 10000;

    /**
     * Build the export rows (events + versions) and signed manifest for a team.
     *
     * @return array{events: array<int, array<string, mixed>>, versions: array<int, array<string, mixed>>, manifest: array<string, mixed>}
     */
    public function export(Team $team): array
    {
        $contractIds = $team->toolContracts()
            ->where('execution_mode', ExecMode::Client)
            ->pluck('id')
            ->all();

        $eventsQuery = ToolImplementationEvent::query()
            ->whereIn('tool_contract_id', $contractIds)
            ->with(['application', 'toolContract'])
            ->latest();

        $totalEvents = (clone $eventsQuery)->count();
        $events = $eventsQuery->limit(self::MAX_ROWS)->get()
            ->map(fn (ToolImplementationEvent $event): array => $this->eventRow($event))
            ->all();

        $versions = ToolContractVersion::query()
            ->whereIn('tool_contract_id', $contractIds)
            ->with('toolContract')
            ->orderByDesc('created_at')
            ->limit(self::MAX_ROWS)
            ->get()
            ->map(fn (ToolContractVersion $version): array => $this->versionRow($version))
            ->all();

        $manifest = [
            'generated_at' => Date::now()->toIso8601String(),
            'team' => $team->slug,
            'event_count' => count($events),
            'version_count' => count($versions),
            'total_events' => $totalEvents,
            'truncated' => $totalEvents > count($events),
            'rows_digest' => hash('sha256', (string) json_encode(['events' => $events, 'versions' => $versions])),
            'signature_key_id' => $this->signer->keyId('export'),
        ];
        $manifest['signature'] = $this->signer->signExport($manifest);

        return ['events' => $events, 'versions' => $versions, 'manifest' => $manifest];
    }

    /**
     * Render an export as a pretty JSON document (manifest + versions + events).
     *
     * @param  array{events: array<int, array<string, mixed>>, versions: array<int, array<string, mixed>>, manifest: array<string, mixed>}  $export
     */
    public function json(array $export): string
    {
        return (string) json_encode([
            'manifest' => $export['manifest'],
            'versions' => $export['versions'],
            'events' => $export['events'],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Render the implementation timeline as an RFC 4180 CSV document (the
     * manifest signature rides an X-header on the response).
     *
     * @param  array{events: array<int, array<string, mixed>>, versions: array<int, array<string, mixed>>, manifest: array<string, mixed>}  $export
     */
    public function csv(array $export): string
    {
        $columns = ['occurred_at', 'tool', 'application', 'environment', 'previous_status', 'status', 'reason', 'reported_version', 'contract_version', 'actor'];
        $lines = [$this->csvRow($columns)];

        foreach ($export['events'] as $row) {
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
     * Flatten one implementation timeline event into an export row.
     *
     * @return array<string, mixed>
     */
    private function eventRow(ToolImplementationEvent $event): array
    {
        return [
            'id' => $event->id,
            'occurred_at' => $event->created_at?->toIso8601String(),
            'tool' => $event->toolContract->name,
            'tool_slug' => $event->toolContract->slug,
            'application' => $event->application->name,
            'application_slug' => $event->application->slug,
            'environment' => $event->environment->value,
            'previous_status' => $event->previous_status?->value,
            'status' => $event->status->value,
            'reason' => $event->reason->value,
            'reported_version' => $event->reported_version,
            'contract_version' => $event->contract_version,
            'actor' => $event->actor_label ?? 'System',
        ];
    }

    /**
     * Flatten one contract version snapshot into an export row.
     *
     * @return array<string, mixed>
     */
    private function versionRow(ToolContractVersion $version): array
    {
        return [
            'tool' => $version->toolContract->name,
            'tool_slug' => $version->toolContract->slug,
            'sequence' => $version->sequence,
            'version' => $version->version,
            'execution_mode' => $version->execution_mode->value,
            'schema_fingerprint' => $version->schema_fingerprint,
            'changed_by' => $version->actor_label ?? 'System',
            'created_at' => $version->created_at?->toIso8601String(),
            'notes' => $version->notes,
        ];
    }
}
