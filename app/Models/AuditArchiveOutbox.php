<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property int $team_id
 * @property string $audit_event_id
 * @property array<string, mixed> $payload
 * @property string $signature
 * @property string $signature_key_id
 * @property string $status
 * @property int $attempts
 * @property Carbon|null $available_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property string|null $error
 */
#[Fillable(['team_id', 'audit_event_id', 'payload', 'signature', 'signature_key_id', 'status', 'attempts', 'available_at', 'delivered_at', 'error'])]
class AuditArchiveOutbox extends Model
{
    use HasUuids;

    protected $table = 'audit_archive_outbox';

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }
}
