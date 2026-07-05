<?php

namespace App\Http\Controllers\Maacc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\AuditExportRequest;
use App\Models\AuditEvent;
use App\Support\Governance\AuditExporter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Enterprise audit export for security review: streams a filtered, signed slice
 * of the team's audit log as JSON or CSV. The export carries a SHA-256 checksum
 * (in the JSON manifest and an `X-Maacc-Audit-Checksum` header) so its integrity
 * can be verified.
 */
class AuditExportController extends Controller
{
    /**
     * Download the filtered audit export.
     */
    public function download(AuditExportRequest $request, AuditExporter $exporter): Response
    {
        Gate::authorize('viewAny', AuditEvent::class);

        $team = $request->user()->currentTeam()->firstOrFail();

        $export = $exporter->export($team, [
            'from' => $request->validated('from'),
            'to' => $request->validated('to'),
            'action' => $request->validated('action'),
            'actor' => $request->validated('actor'),
        ]);

        $format = $request->validated('format', 'json');
        $timestamp = now()->format('Ymd-His');

        if ($format === 'csv') {
            $body = $exporter->csv($export);
            $contentType = 'text/csv';
            $filename = "maacc-audit-{$team->slug}-{$timestamp}.csv";
        } else {
            $body = $exporter->json($export);
            $contentType = 'application/json';
            $filename = "maacc-audit-{$team->slug}-{$timestamp}.json";
        }

        return response($body, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Maacc-Audit-Checksum' => $export['manifest']['checksum'],
            'X-Maacc-Audit-Count' => (string) $export['manifest']['count'],
        ]);
    }
}
