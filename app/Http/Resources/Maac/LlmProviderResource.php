<?php

namespace App\Http\Resources\Maac;

use App\Enums\Environment;
use App\Models\LlmProvider;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes an LlmProvider to the Phase 1 console contract shape
 * (resources/js/maac/data.ts `Llm`).
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
        ];
    }
}
