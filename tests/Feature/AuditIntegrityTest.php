<?php

use App\Jobs\DeliverAuditArchive;
use App\Models\AuditArchiveOutbox;
use App\Models\AuditChainHead;
use App\Models\AuditEvent;
use App\Models\Team;
use App\Support\Governance\AuditIntegrityVerifier;
use App\Support\Governance\AuditLedger;
use App\Support\Governance\AuditSigner;
use App\Support\Governance\Contracts\AuditArchive;
use App\Support\Governance\FilesystemAuditArchive;
use Illuminate\Filesystem\FilesystemAdapter;
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

test('enterprise production refuses a local or unattested audit archive', function () {
    app()->detectEnvironment(fn (): string => 'production');
    config([
        'maacc.readiness.status' => 'enterprise',
        'maacc.audit.chain_key' => 'independent-test-chain-key',
        'maacc.audit.archive_immutable_enforced' => false,
        'filesystems.disks.audit_archive.driver' => 'local',
    ]);

    expect(fn () => appendAudit('agent.created'))
        ->toThrow(RuntimeException::class, 'not configured and attested as immutable');
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

test('the verification command renders human-readable success evidence', function () {
    appendAudit('agent.created');

    $this->artisan('maacc:verify-audit', ['--team' => $this->team->slug])
        ->assertSuccessful()
        ->expectsOutputToContain($this->team->slug);
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

test('legal holds reject malformed and non-future deadlines', function () {
    $this->artisan('maacc:audit-legal-hold', [
        'team' => $this->team->slug,
        '--until' => 'not-a-date',
        '--reason' => 'LEGAL-INVALID',
    ])->assertExitCode(2);

    $this->artisan('maacc:audit-legal-hold', [
        'team' => $this->team->slug,
        '--until' => now()->subDay()->toIso8601String(),
        '--reason' => 'LEGAL-PAST',
    ])->assertExitCode(2);
});

test('verification reports a missing chain head and wholly missing sequence', function () {
    $otherTeam = Team::factory()->create();
    expect($this->verifier->verify($otherTeam)['errors'][0]['code'])->toBe('chain_head_missing');

    $event = appendAudit('agent.created');
    AuditArchiveOutbox::query()->where('audit_event_id', $event->id)->delete();
    DB::table('audit_events')->where('id', $event->id)->delete();

    expect(collect($this->verifier->verify($this->team)['errors'])->pluck('code'))
        ->toContain('event_deleted_or_missing');
});

test('verification reports ordering links delivery states and a missing outbox', function () {
    $first = appendAudit('agent.created');
    $second = appendAudit('agent.updated');

    DB::table('audit_events')->where('id', $second->id)->update(['previous_signature' => str_repeat('a', 64)]);
    AuditArchiveOutbox::query()->where('audit_event_id', $first->id)->update(['status' => 'failed']);
    AuditArchiveOutbox::query()->where('audit_event_id', $second->id)->delete();

    $codes = collect($this->verifier->verify($this->team)['errors'])->pluck('code');
    expect($codes)->toContain('chain_link_invalid', 'archive_delivery_failed', 'archive_outbox_missing');

    $outbox = AuditArchiveOutbox::query()->where('audit_event_id', $first->id)->firstOrFail();
    $outbox->update(['status' => 'pending']);
    expect(collect($this->verifier->verify($this->team)['errors'])->pluck('code'))
        ->toContain('archive_delivery_pending');
});

test('verification detects a signer-supplied reordered payload', function () {
    $event = appendAudit('agent.created');
    $signer = Mockery::mock(AuditSigner::class);
    $signer->shouldReceive('eventPayload')->andReturn([
        'id' => $event->id,
        'sequence' => 9,
        'previous_signature' => 'wrong-link',
    ]);
    $signer->shouldReceive('verifyPayload')->andReturnTrue();
    $archive = Mockery::mock(AuditArchive::class);
    $archive->shouldReceive('exists')->andReturnTrue();
    $verifier = new AuditIntegrityVerifier($signer, $archive);

    $codes = collect($verifier->verify($this->team)['errors'])->pluck('code');
    expect($codes)->toContain('event_reordered', 'chain_link_invalid');
});

test('audit signer enforces enterprise keys and accepts a retained verification key', function () {
    $signer = app(AuditSigner::class);
    config([
        'maacc.audit.chain_verification_keys' => ['old-chain' => 'old-secret'],
    ]);
    $payload = $signer->eventPayload(['id' => 'event-1', 'sequence' => 1, 'action' => 'test']);
    $signature = hash_hmac('sha256', $signer->canonical($payload), 'old-secret');

    expect($signer->verifyPayload($payload, $signature, 'old-chain'))->toBeTrue();

    app()->detectEnvironment(fn (): string => 'production');
    config(['maacc.readiness.status' => 'enterprise', 'maacc.audit.chain_key' => null]);
    expect(fn () => $signer->signEvent([]))
        ->toThrow(RuntimeException::class, 'key is not configured');
});

test('audit archive delivery is idempotent and rejects divergent immutable objects', function () {
    $event = appendAudit('agent.created');
    $outbox = AuditArchiveOutbox::firstWhere('audit_event_id', $event->id);
    $archive = app(AuditArchive::class);

    expect($archive->archive($outbox))->toStartWith('sha256:');
    $path = Storage::disk('audit_archive')->allFiles()[0];
    Storage::disk('audit_archive')->put($path, 'tampered');

    expect(fn () => $archive->archive($outbox))
        ->toThrow(RuntimeException::class, 'different content');
});

test('audit archive jobs ignore completed or removed work and reject invalid sources', function () {
    $event = appendAudit('agent.created');
    $outbox = AuditArchiveOutbox::firstWhere('audit_event_id', $event->id);
    $job = new DeliverAuditArchive($outbox);

    $job->handle(app(AuditArchive::class), app(AuditSigner::class));
    $outbox->delete();
    $job->handle(app(AuditArchive::class), app(AuditSigner::class));

    $event = appendAudit('agent.updated');
    $outbox = AuditArchiveOutbox::firstWhere('audit_event_id', $event->id);
    $outbox->update(['status' => 'pending', 'signature' => str_repeat('f', 64)]);

    expect(fn () => (new DeliverAuditArchive($outbox))->handle(app(AuditArchive::class), app(AuditSigner::class)))
        ->toThrow(RuntimeException::class, 'invalid or missing source event');
});

test('the audit archive fails closed when storage rejects a new immutable object', function () {
    $event = appendAudit('agent.created');
    $outbox = AuditArchiveOutbox::firstWhere('audit_event_id', $event->id);
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('exists')->once()->andReturnFalse();
    $disk->shouldReceive('put')->once()->andReturnFalse();
    Storage::shouldReceive('disk')->with('failing_archive')->andReturn($disk);
    config(['maacc.audit.archive_disk' => 'failing_archive']);

    expect(fn () => (new FilesystemAuditArchive)->archive($outbox))
        ->toThrow(RuntimeException::class, 'rejected the event');
});
