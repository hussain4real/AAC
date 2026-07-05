<?php

namespace App\Http\Resources\Maacc;

use App\Models\VaultSecret;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a vault-held secret for the console. The plaintext value is never
 * exposed — only the display hint (last characters), version, rotation, and
 * access metadata — so security reviewers can audit the inventory and rotation
 * cadence without ever handling secret material.
 *
 * @mixin VaultSecret
 */
class VaultSecretResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'uuid' => $this->id,
            'id' => $this->id,
            'name' => $this->name,
            'reference' => $this->reference,
            'kind' => $this->kind->value,
            'kindLabel' => $this->kind->label(),
            'lastFour' => $this->last_four,
            'version' => $this->version,
            'boundModel' => $this->whenLoaded('llmProviders', fn () => $this->llmProviders->pluck('name')->all(), []),
            'rotatedAt' => $this->rotated_at?->diffForHumans(),
            'lastAccessed' => $this->last_accessed_at?->diffForHumans(),
            'accessedCount' => $this->accessed_count,
            'createdBy' => $this->whenLoaded('creator', fn () => $this->creator?->name),
            'createdAt' => $this->created_at?->format('j M Y'),
        ];
    }
}
