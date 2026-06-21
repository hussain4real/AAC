<?php

namespace App\Http\Resources\Maac;

use App\Models\AuditEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Serializes an AuditEvent for the console audit log: who did what, to which
 * record, when, and in which environment.
 *
 * @mixin AuditEvent
 */
class AuditEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action,
            'label' => Str::headline(str_replace('.', ' ', $this->action)),
            'actor' => $this->actor_label ?? 'System',
            'target' => $this->auditable_type !== null ? class_basename($this->auditable_type) : null,
            'environment' => $this->environment?->label(),
            'time' => $this->created_at?->diffForHumans() ?? '—',
            'at' => $this->created_at?->format('d M Y H:i'),
            'metadata' => $this->metadata,
        ];
    }
}
