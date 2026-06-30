<?php

namespace App\Console\Commands;

use App\Models\PlatformAccessGrant;
use App\Support\Platform\PlatformAccessManager;
use Illuminate\Console\Command;

/**
 * Reviews MAAC platform access (Phase 8B): revokes every break-glass grant whose
 * window has elapsed, then reports the standard grants that need re-certification
 * and the platform admins with no recent activity (stale accounts). Intended to
 * run on a schedule so emergency access never lingers and access certification
 * stays current.
 */
class ReviewPlatformAccessCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maac:review-platform-access {--json : Output the review as JSON}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Expire elapsed break-glass grants and report uncertified and stale platform access';

    /**
     * Execute the console command.
     */
    public function handle(PlatformAccessManager $access): int
    {
        $expired = $access->expireDueGrants();
        $needingCertification = $access->needingCertification();
        $stale = $access->staleGrants();

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'expired' => $expired,
                'needing_certification' => $needingCertification->map($this->grantSummary(...))->all(),
                'stale' => $stale->map($this->grantSummary(...))->all(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->components->info("Platform access review — {$expired} elapsed break-glass grant(s) revoked.");

        $this->components->twoColumnDetail('<options=bold>Needs certification</>', (string) $needingCertification->count());
        $this->table(
            ['User', 'Role', 'Last certified'],
            $needingCertification->map(fn (PlatformAccessGrant $g): array => [
                $g->user->email,
                $g->role->value,
                $g->certified_at?->toDateString() ?? 'never',
            ])->all(),
        );

        $this->components->twoColumnDetail('<options=bold>Stale admins</>', (string) $stale->count());
        $this->table(
            ['User', 'Role', 'Granted'],
            $stale->map(fn (PlatformAccessGrant $g): array => [
                $g->user->email,
                $g->role->value,
                $g->created_at?->toDateString() ?? '—',
            ])->all(),
        );

        return self::SUCCESS;
    }

    /**
     * Summarize a grant for the JSON report.
     *
     * @return array{user: string, role: string, certified_at: string|null}
     */
    private function grantSummary(PlatformAccessGrant $grant): array
    {
        return [
            'user' => $grant->user->email,
            'role' => $grant->role->value,
            'certified_at' => $grant->certified_at?->toIso8601String(),
        ];
    }
}
