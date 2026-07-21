<?php

namespace App\Support\Governance;

use App\Models\AuditArchiveOutbox;
use App\Support\Governance\Contracts\AuditArchive;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Immutable-object archive adapter. Production points its dedicated disk at an
 * object-lock/WORM bucket or SIEM sink; every event has a unique deterministic
 * key and an existing object is verified, never overwritten.
 */
class FilesystemAuditArchive implements AuditArchive
{
    public function archive(AuditArchiveOutbox $outbox): string
    {
        $diskName = (string) config('maacc.audit.archive_disk');
        $this->ensureEnterpriseArchive($diskName);
        $disk = Storage::disk($diskName);
        $path = $this->path($outbox);
        $body = (string) json_encode([
            'event' => $outbox->payload,
            'signature' => $outbox->signature,
            'signature_key_id' => $outbox->signature_key_id,
        ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        if ($disk->exists($path)) {
            if (! hash_equals(hash('sha256', $body), hash('sha256', (string) $disk->get($path)))) {
                throw new RuntimeException('The immutable audit archive object already exists with different content.');
            }

            return 'sha256:'.hash('sha256', $body);
        }

        if (! $disk->put($path, $body)) {
            throw new RuntimeException('The immutable audit archive rejected the event.');
        }

        return 'sha256:'.hash('sha256', $body);
    }

    public function exists(AuditArchiveOutbox $outbox): bool
    {
        return Storage::disk((string) config('maacc.audit.archive_disk'))->exists($this->path($outbox));
    }

    private function path(AuditArchiveOutbox $outbox): string
    {
        $sequence = str_pad((string) ($outbox->payload['sequence'] ?? 0), 20, '0', STR_PAD_LEFT);

        return "teams/{$outbox->team_id}/{$sequence}-{$outbox->audit_event_id}.json";
    }

    private function ensureEnterpriseArchive(string $diskName): void
    {
        if (! App::environment('production') || config('maacc.readiness.status') !== 'enterprise') {
            return;
        }

        $driver = config("filesystems.disks.{$diskName}.driver");

        if ($driver !== 's3' || ! (bool) config('maacc.audit.archive_immutable_enforced', false)) {
            throw new RuntimeException('The enterprise audit archive is not configured and attested as immutable object storage.');
        }
    }
}
