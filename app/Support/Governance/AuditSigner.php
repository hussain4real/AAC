<?php

namespace App\Support\Governance;

use App\Models\AuditEvent;
use Illuminate\Support\Facades\App;
use RuntimeException;

/** Independently keyed HMAC signing for the audit chain and export manifests. */
class AuditSigner
{
    /**
     * @param  array<string, mixed>|AuditEvent  $event
     * @return array<string, mixed>
     */
    public function eventPayload(array|AuditEvent $event): array
    {
        $values = $event instanceof AuditEvent ? $event->getAttributes() : $event;

        return [
            'id' => (string) ($values['id'] ?? ''),
            'team_id' => (int) ($values['team_id'] ?? 0),
            'sequence' => (int) ($values['sequence'] ?? 0),
            'previous_signature' => $values['previous_signature'] ?? null,
            'actor_user_id' => isset($values['actor_user_id']) ? (int) $values['actor_user_id'] : null,
            'actor_label' => $values['actor_label'] ?? null,
            'action' => (string) ($values['action'] ?? ''),
            'auditable_type' => $values['auditable_type'] ?? null,
            'auditable_id' => $values['auditable_id'] ?? null,
            'environment' => $values['environment'] ?? null,
            'metadata' => $this->decode($values['metadata'] ?? null),
            'ip_address' => $values['ip_address'] ?? null,
        ];
    }

    /** @param array<string, mixed>|AuditEvent $event */
    public function signEvent(array|AuditEvent $event): string
    {
        return hash_hmac('sha256', $this->canonical($this->eventPayload($event)), $this->key('chain'));
    }

    public function verifyEvent(AuditEvent $event): bool
    {
        $key = $this->verificationKey('chain', (string) $event->signature_key_id);

        return $event->signature !== null
            && $key !== null
            && hash_equals($event->signature, hash_hmac('sha256', $this->canonical($this->eventPayload($event)), $key));
    }

    /** @param array<string, mixed> $payload */
    public function verifyPayload(array $payload, string $signature, string $keyId): bool
    {
        $key = $this->verificationKey('chain', $keyId);

        return $key !== null
            && hash_equals($signature, hash_hmac('sha256', $this->canonical($this->eventPayload($payload)), $key));
    }

    /** @param array<string, mixed> $manifest */
    public function signExport(array $manifest): string
    {
        return hash_hmac('sha256', $this->canonical($manifest), $this->key('export'));
    }

    /** @param array<string, mixed> $manifest */
    public function verifyExport(array $manifest, string $signature): bool
    {
        $keyId = is_string($manifest['signature_key_id'] ?? null) ? $manifest['signature_key_id'] : '';
        $key = $this->verificationKey('export', $keyId);

        return $key !== null
            && hash_equals($signature, hash_hmac('sha256', $this->canonical($manifest), $key));
    }

    public function keyId(string $purpose): string
    {
        return (string) config("maacc.audit.{$purpose}_key_id");
    }

    /** @param array<string, mixed> $value */
    public function canonical(array $value): string
    {
        $normalized = $this->sort($value);

        return (string) json_encode($normalized, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    private function key(string $purpose): string
    {
        $key = config("maacc.audit.{$purpose}_key");

        if (is_string($key) && $key !== '') {
            return $key;
        }

        if (App::environment('production') && config('maacc.readiness.status') === 'enterprise') {
            throw new RuntimeException("The independent MAACC audit {$purpose} key is not configured.");
        }

        return hash_hmac('sha256', "maacc-audit-{$purpose}", (string) config('app.key'));
    }

    private function verificationKey(string $purpose, string $keyId): ?string
    {
        if ($keyId === $this->keyId($purpose)) {
            return $this->key($purpose);
        }

        $previous = config("maacc.audit.{$purpose}_verification_keys", []);

        return is_array($previous) && is_string($previous[$keyId] ?? null) && $previous[$keyId] !== ''
            ? $previous[$keyId]
            : null;
    }

    private function decode(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }

    private function sort(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(fn (mixed $item): mixed => $this->sort($item), $value);
    }
}
