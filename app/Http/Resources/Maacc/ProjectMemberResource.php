<?php

namespace App\Http\Resources\Maacc;

use App\Models\ProjectMember;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ProjectMember */
class ProjectMemberResource extends JsonResource
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
            'userId' => $this->user_id,
            'name' => $this->whenLoaded('user', fn (): string => $this->user->name),
            'email' => $this->whenLoaded('user', fn (): string => $this->user->email),
            'role' => $this->maacc_role?->value,
            'roleLabel' => $this->maacc_role?->label(),
            'active' => $this->isActive(),
            'expiresAt' => $this->expires_at?->toIso8601String(),
            'revokedAt' => $this->revoked_at?->toIso8601String(),
            'certifiedAt' => $this->certified_at?->toIso8601String(),
            'grantor' => $this->whenLoaded('grantor', fn (): ?string => $this->grantor?->name),
            'revoker' => $this->whenLoaded('revoker', fn (): ?string => $this->revoker?->name),
            'certifier' => $this->whenLoaded('certifier', fn (): ?string => $this->certifier?->name),
            'reason' => $this->reason,
            'certificationNote' => $this->certification_note,
        ];
    }
}
