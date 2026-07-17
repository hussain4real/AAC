<?php

namespace App\Console\Commands;

use App\Support\Sso\SsoAccessIntegrityScanner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('maacc:audit-sso-access {--json : Output a machine-readable report}')]
#[Description('Read-only audit of privileged SSO identities, sessions, recovery factors, and access-ledger drift')]
class AuditSsoAccess extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(SsoAccessIntegrityScanner $scanner): int
    {
        $report = $scanner->scan();

        if ($this->option('json')) {
            $this->line((string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            $this->components->info('Privileged SSO identity and session audit completed in read-only mode.');
            $this->line("Privileged users: {$report['privileged_user_count']}");
            $this->line("Privileged SSO identities: {$report['privileged_identity_count']}");
            $this->line("Active sessions: {$report['active_session_count']}");
            $this->line("Findings: {$report['finding_count']}");

            if ($report['findings'] !== []) {
                $this->table(
                    ['Severity', 'Code', 'User ID', 'Identity ID', 'Message'],
                    array_map(fn (array $finding): array => [
                        $finding['severity'],
                        $finding['code'],
                        $finding['user_id'],
                        $finding['identity_id'],
                        $finding['message'],
                    ], $report['findings']),
                );
            }
        }

        return $report['finding_count'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
