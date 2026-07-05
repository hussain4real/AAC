<?php

namespace App\Http\Resources\Maacc;

use App\Models\IncidentAction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/**
 * Serializes a break-glass / incident-response action for the incidents console:
 * what was done, to which subject, by whom, why, and whether it has been
 * reverted. This is the immutable incident timeline a security reviewer reads.
 *
 * @mixin IncidentAction
 */
class IncidentActionResource extends JsonResource
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
            'type' => $this->type->value,
            'typeLabel' => $this->type->label(),
            'severity' => $this->type->severity()->value,
            'actor' => $this->actor_label ?? 'System',
            'subject' => $this->subject_label,
            'subjectType' => $this->subject_type !== null ? class_basename($this->subject_type) : null,
            'reason' => $this->reason,
            'environment' => $this->environment?->label(),
            'reverted' => $this->isReverted(),
            'revertedAt' => $this->reverted_at?->diffForHumans(),
            'time' => $this->created_at?->diffForHumans() ?? '—',
            'at' => $this->created_at?->format('d M Y H:i'),
            'action' => Str::headline($this->type->value),
        ];
    }
}
