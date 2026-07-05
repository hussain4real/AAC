<?php

namespace App\Http\Controllers\Maacc;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\ExportVersionJourneyRequest;
use App\Models\ToolContract;
use App\Support\Sdk\VersionJourneyExporter;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

/**
 * Downloads the team's tool version journey — the contract version snapshots and
 * the implementation event timeline for its client-side tools — as a signed
 * JSON or CSV export. The export carries a SHA-256 checksum (in the JSON
 * manifest and an `X-Maacc-Journey-Checksum` header) so its integrity can be
 * verified after the fact.
 */
class VersionJourneyExportController extends Controller
{
    /**
     * Download the version journey export.
     */
    public function download(ExportVersionJourneyRequest $request, VersionJourneyExporter $exporter): Response
    {
        Gate::authorize('viewAny', ToolContract::class);

        $team = $request->user()->currentTeam()->firstOrFail();

        $export = $exporter->export($team);

        $format = $request->validated('format', 'json');
        $timestamp = now()->format('Ymd-His');

        if ($format === 'csv') {
            $body = $exporter->csv($export);
            $contentType = 'text/csv';
            $filename = "maacc-version-journey-{$team->slug}-{$timestamp}.csv";
        } else {
            $body = $exporter->json($export);
            $contentType = 'application/json';
            $filename = "maacc-version-journey-{$team->slug}-{$timestamp}.json";
        }

        return response($body, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'X-Maacc-Journey-Checksum' => $export['manifest']['checksum'],
            'X-Maacc-Journey-Event-Count' => (string) $export['manifest']['event_count'],
        ]);
    }
}
