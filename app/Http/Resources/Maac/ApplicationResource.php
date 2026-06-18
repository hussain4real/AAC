<?php

namespace App\Http\Resources\Maac;

use App\Models\Application;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes an Application to the Phase 1 console contract shape
 * (resources/js/maac/data.ts `Application`).
 *
 * @mixin Application
 */
class ApplicationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->slug,
            'name' => $this->name,
            'code' => $this->code,
            'dept' => $this->department,
            'owner' => $this->owner_name,
            'ownerEmail' => $this->owner_email,
            'env' => $this->environment->label(),
            'status' => $this->status->label(),
            'projects' => $this->projects_count,
            'agents' => $this->agents_count,
            'toolsRequired' => $this->tools_required,
            'toolsImplemented' => $this->tools_implemented,
            'lastConnected' => $this->last_connected_at?->diffForHumans() ?? '—',
            'stack' => $this->stack,
            'desc' => $this->description,
            'credStatus' => $this->whenLoaded('credentials', fn () => $this->credentialStatus()),
            'lastSyncedAt' => $this->whenLoaded('credentials', fn () => $this->lastSyncedAt()),
            'region' => $this->region,
            'created' => $this->created_at?->format('j M Y') ?? '',
        ];
    }
}
