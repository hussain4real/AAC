<?php

use App\Models\AuditArchiveOutbox;
use App\Models\AuditChainHead;
use App\Models\AuditEvent;
use App\Support\Governance\AuditIntegrityVerifier;
use App\Support\Governance\AuditLedger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('audit_archive');
    [$this->owner, $this->team] = ownerAndTeam();
    $this->ledger = app(AuditLedger::class);
    $this->verifier = app(AuditIntegrityVerifier::class);
});

function appendAudit(string $action): AuditEvent
{
    return test()->ledger->record([
        'team_id' => test()->team->id,
        'actor_user_id' => test()->owner->id,
        'actor_label' => test()->owner->name,
        'action' => $action,
        'auditable_type' => null,
        'auditable_id' => null,
        'metadata' => ['correlation_id' => 'corr-'.$action],
        'ip_address' => '127.0.0.1',
    ]);
}

test('the ledger atomically signs chains and archives audit events', function () {
    appendAudit('agent.created');
    appendAudit('agent.updated');
    appendAudit('agent.published');

    $result = $this->verifier->verify($this->team);

    expect($result['valid'])->toBeTrue()
        ->and($result['verified'])->toBe(3)
        ->and(AuditArchiveOutbox::where('status', 'delivered')->count())->toBe(3)
        ->and(AuditEvent::whereNotNull('archive_receipt')->count())->toBe(3)
        ->and(AuditChainHead::find($this->team->id)->next_sequence)->toBe(4);
});

test('independent verification detects tampering and archive payload divergence', function () {
    $event = appendAudit('agent.created');

    DB::table('audit_events')->where('id', $event->id)->update(['action' => 'agent.deleted']);

    $result = $this->verifier->verify($this->team);
    $codes = collect($result['errors'])->pluck('code');

    expect($result['valid'])->toBeFalse()
        ->and($codes)->toContain('signature_or_key_invalid')
        ->toContain('archive_payload_mismatch');
});

test('independent verification detects early deletion and chain recovery failure', function () {
    $event = appendAudit('agent.created');

    DB::table('audit_events')->where('id', $event->id)->delete();
    AuditChainHead::query()->whereKey($this->team->id)->update(['last_signature' => str_repeat('0', 64)]);

    $codes = collect($this->verifier->verify($this->team)['errors'])->pluck('code');

    expect($codes)->toContain('event_deleted_before_retention')
        ->toContain('chain_head_mismatch');
});

test('independent verification detects exporter key misuse and archive loss', function () {
    $event = appendAudit('agent.created');
    $outbox = AuditArchiveOutbox::firstWhere('audit_event_id', $event->id);

    DB::table('audit_events')->where('id', $event->id)->update(['signature_key_id' => 'unauthorized-key']);
    Storage::disk('audit_archive')->delete(Storage::disk('audit_archive')->allFiles()[0]);

    $codes = collect($this->verifier->verify($this->team)['errors'])->pluck('code');

    expect($outbox)->not->toBeNull()
        ->and($codes)->toContain('signature_or_key_invalid')
        ->toContain('archive_object_missing');
});

test('the verification command emits machine-readable failure evidence', function () {
    $event = appendAudit('agent.created');
    DB::table('audit_events')->where('id', $event->id)->update(['signature' => str_repeat('f', 64)]);

    $this->artisan('maacc:verify-audit', ['--team' => $this->team->slug, '--json' => true])
        ->assertFailed()
        ->expectsOutputToContain('signature_or_key_invalid');
});

test('legal holds require a reason and are applied and released with signed audit evidence', function () {
    $event = appendAudit('agent.created');
    $until = now()->addDays(30)->toIso8601String();

    $this->artisan('maacc:audit-legal-hold', [
        'team' => $this->team->slug,
        '--until' => $until,
    ])->assertExitCode(2);

    $this->artisan('maacc:audit-legal-hold', [
        'team' => $this->team->slug,
        '--until' => $until,
        '--reason' => 'LEGAL-2026-0042',
    ])->assertSuccessful();

    expect($event->fresh()->legal_hold_until)->not->toBeNull()
        ->and(AuditEvent::query()->where('action', 'audit.legal_hold_applied')->firstOrFail()->legal_hold_until)->not->toBeNull();

    $this->artisan('maacc:audit-legal-hold', [
        'team' => $this->team->slug,
        '--release' => true,
        '--reason' => 'LEGAL-2026-0042 closed',
    ])->assertSuccessful();

    expect(AuditEvent::query()->where('team_id', $this->team->id)->whereNotNull('legal_hold_until')->count())->toBe(0)
        ->and(AuditEvent::query()->where('action', 'audit.legal_hold_released')->exists())->toBeTrue();
});
