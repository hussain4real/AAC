<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class ProductReadinessProbe
{
    /** Prove the database and representative authenticated-route assets are usable. */
    public function assertReady(): void
    {
        DB::select('select 1');

        $manifestPath = (string) config(
            'maacc.readiness.asset_manifest',
            public_path('build/manifest.json'),
        );

        if (! is_file($manifestPath) || ! is_readable($manifestPath)) {
            throw new RuntimeException('The frontend asset manifest is unavailable.');
        }

        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $requiredEntries = [
            'resources/js/app.tsx',
            'resources/js/pages/dashboard.tsx',
        ];

        if (! is_array($manifest)) {
            throw new RuntimeException('The frontend asset manifest is invalid.');
        }

        foreach ($requiredEntries as $entry) {
            $asset = $manifest[$entry]['file'] ?? null;

            if (! is_string($asset) || ! is_file(public_path('build/'.$asset))) {
                throw new RuntimeException("The representative frontend asset [{$entry}] is unavailable.");
            }
        }
    }
}
