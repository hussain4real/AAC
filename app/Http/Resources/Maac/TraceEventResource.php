<?php

namespace App\Http\Resources\Maac;

use App\Models\TraceEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a TraceEvent for the run detail/trace view.
 *
 * @mixin TraceEvent
 */
class TraceEventResource extends JsonResource
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
            'label' => $this->type->label(),
            'message' => $this->message,
            'data' => $this->data,
            'sequence' => $this->sequence,
            'occurredAt' => $this->occurred_at?->format('H:i:s'),
        ];
    }
}
