<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Support\Governance\AuditIntegrityVerifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('maacc:verify-audit {--team= : Verify one team slug} {--json : Emit machine-readable evidence}')]
#[Description('Verify audit signatures, ordering, completeness, archive delivery, and recovery state')]
class VerifyAuditIntegrity extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(AuditIntegrityVerifier $verifier): int
    {
        $query = Team::query();

        if (is_string($this->option('team')) && $this->option('team') !== '') {
            $query->where('slug', $this->option('team'));
        }

        $results = [];
        $valid = true;

        foreach ($query->get() as $team) {
            $result = $verifier->verify($team);
            $results[$team->slug] = $result;
            $valid = $valid && $result['valid'];
        }

        if ($this->option('json')) {
            $this->line((string) json_encode(['valid' => $valid, 'teams' => $results], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        } else {
            foreach ($results as $slug => $result) {
                $this->components->twoColumnDetail($slug, $result['valid'] ? '<fg=green>valid</>' : '<fg=red>invalid</>');
            }
        }

        return $valid ? self::SUCCESS : self::FAILURE;
    }
}
