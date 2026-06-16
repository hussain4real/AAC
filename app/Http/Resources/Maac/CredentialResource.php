<?php

namespace App\Http\Resources\Maac;

use App\Models\Credential;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a Credential's safe metadata. The hashed secret is never exposed;
 * the one-time plaintext is flashed separately by the controller on creation.
 *
 * @mixin Credential
 */
class CredentialResource extends JsonResource
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
            'environment' => $this->environment->label(),
            'label' => $this->label,
            'clientId' => $this->client_id,
            'lastFour' => $this->last_four,
            'status' => $this->status->label(),
            'lastUsedAt' => $this->last_used_at?->diffForHumans(),
            'rotatedAt' => $this->rotated_at?->diffForHumans(),
            'revokedAt' => $this->revoked_at?->diffForHumans(),
            'createdAt' => $this->created_at?->format('j M Y'),
        ];
    }
}
