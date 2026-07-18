<?php

namespace App\Console\Commands;

use App\Models\AuditEvent;
use App\Models\Team;
use App\Support\Governance\AuditLedger;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Date;
use Throwable;

#[Signature('maacc:audit-legal-hold {team : Team slug} {--until= : ISO date/time through which records are held} {--release : Release the current hold} {--reason= : Required case/reason reference}')]
#[Description('Apply or release an audited tenant legal hold on local audit records')]
class ManageAuditLegalHold extends Command
{
    public function handle(AuditLedger $ledger): int
    {
        $reason = trim((string) $this->option('reason'));

        if ($reason === '') {
            $this->error('A legal case or approved reason is required.');

            return self::INVALID;
        }

        $team = Team::query()->where('slug', $this->argument('team'))->firstOrFail();
        $release = (bool) $this->option('release');

        try {
            $until = $release ? null : Date::parse((string) $this->option('until'));
        } catch (Throwable) {
            $this->error('Provide a valid --until date/time, or use --release.');

            return self::INVALID;
        }

        if (! $release && ($this->option('until') === null || ! $until?->isFuture())) {
            $this->error('An active legal hold requires a future --until date/time.');

            return self::INVALID;
        }

        AuditEvent::query()->where('team_id', $team->id)->update(['legal_hold_until' => $until]);
        $event = $ledger->record([
            'team_id' => $team->id,
            'action' => $release ? 'audit.legal_hold_released' : 'audit.legal_hold_applied',
            'actor_label' => 'CLI operator',
            'metadata' => ['reason' => $reason, 'until' => $until?->toIso8601String()],
        ]);

        if ($until !== null) {
            $event->update(['legal_hold_until' => $until]);
        }

        $this->info($release ? 'Legal hold released.' : "Legal hold applied through {$until->toIso8601String()}.");

        return self::SUCCESS;
    }
}
