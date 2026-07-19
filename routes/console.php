<?php

use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');

Schedule::command('maacc:prune-run-data')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Prune run payloads and audit events past governance retention windows');

Schedule::command('maacc:recover-runs')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer()
    ->description('Expire abandoned runs and recover stale worker claims');

Schedule::command('maacc:maintain-runtime')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer()
    ->description('Expire stale approvals and recover webhook, audit, token, and ingestion state');

Schedule::command('maacc:verify-audit --json')
    ->hourly()
    ->withoutOverlapping(30)
    ->onOneServer()
    ->description('Detect audit tampering, deletion, reordering, archive loss, and recovery failure');

Schedule::command('queue:prune-failed --hours=168')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Prune failed queue jobs after the seven-day repair window');

Schedule::command('maacc:review-platform-access')
    ->daily()
    ->withoutOverlapping()
    ->onOneServer()
    ->description('Expire elapsed break-glass grants and flag platform access for review');
