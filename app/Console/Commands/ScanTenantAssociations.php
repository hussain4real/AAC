<?php

namespace App\Console\Commands;

use App\Support\Governance\TenantAssociationIntegrityScanner;
use Illuminate\Console\Command;

class ScanTenantAssociations extends Command
{
    /**
     * @var string
     */
    protected $signature = 'maacc:scan-tenant-integrity {--json : Output a machine-readable report}';

    /**
     * @var string
     */
    protected $description = 'Read-only scan for cross-tenant and invalid parent associations';

    public function handle(TenantAssociationIntegrityScanner $scanner): int
    {
        $findings = $scanner->scan();
        $report = [
            'read_only' => true,
            'scanned_at' => now()->toIso8601String(),
            'finding_count' => count($findings),
            'findings' => $findings,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } elseif ($findings === []) {
            $this->components->info('Tenant association integrity scan passed with no findings.');
        } else {
            $this->components->error('Tenant association integrity scan found '.count($findings).' mismatch(es).');
            $this->table(
                ['Code', 'Model', 'ID', 'Message'],
                array_map(fn (array $finding): array => array_values($finding), $findings),
            );
        }

        return $findings === [] ? self::SUCCESS : self::FAILURE;
    }
}
