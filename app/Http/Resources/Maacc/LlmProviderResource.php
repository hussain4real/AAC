<?php

namespace App\Http\Resources\Maacc;

use App\Enums\Environment;
use App\Models\LlmProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes an LlmProvider to the Phase 1 console contract shape
 * (resources/js/maacc/data.ts `Llm`).
 *
 * @mixin LlmProvider
 */
class LlmProviderResource extends JsonResource
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
            'id' => $this->slug,
            'name' => $this->name,
            'code' => $this->code,
            'provider' => $this->provider,
            'ctx' => $this->context_window,
            'inCost' => $this->input_cost,
            'outCost' => $this->output_cost,
            'sensitivity' => $this->sensitivity->label(),
            'envs' => array_map(
                fn (string $env): string => Environment::from($env)->label(),
                $this->environments,
            ),
            'status' => $this->status->label(),
            'usagePct' => $this->usage_pct,
            'runs' => $this->runs_count,
            'note' => $this->note,
            'hasKey' => $this->vault_secret_id !== null,
            'keyLastFour' => $this->whenLoaded('vaultSecret', fn (): ?string => $this->vaultSecret?->last_four),
            'verification' => [
                'status' => $this->verification_status?->value,
                'label' => $this->verification_status?->label(),
                'focus' => $this->verification_status?->focus(),
                'message' => $this->verification_message,
                'verifiedAt' => $this->verified_at?->toIso8601String(),
                'checkedAt' => $this->verification_checked_at?->toIso8601String(),
            ],
        ];
    }
}
