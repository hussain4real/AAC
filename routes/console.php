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
    ->description('Prune run payloads and audit events past governance retention windows');

Schedule::command('maacc:review-platform-access')
    ->daily()
    ->description('Expire elapsed break-glass grants and flag platform access for review');
