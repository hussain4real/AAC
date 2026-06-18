<?php

namespace App\Http\Resources\Maac;

use App\Models\ToolContract;
use App\Models\ToolImplementation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a ToolContract to the Phase 1 console contract shape
 * (resources/js/maac/data.ts `Tool`). `execMode` and `impl` keep their raw
 * enum values because the frontend keys its label maps on them.
 *
 * @mixin ToolContract
 */
class ToolContractResource extends JsonResource
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
            'scope' => $this->scope->label(),
            'execMode' => $this->execution_mode->value,
            'sensitivity' => $this->sensitivity->label(),
            'approval' => $this->requires_approval,
            'status' => $this->status,
            'impl' => $this->implementation_status->value,
            'owner' => $this->ownerLabel(),
            'appId' => $this->whenLoaded('application', fn () => $this->application?->slug),
            'usedBy' => $this->whenLoaded('agents', fn () => $this->agents->pluck('slug')->all()),
            'desc' => $this->description,
            'timeout' => $this->timeout_seconds.'s',
            'maxPayload' => $this->formattedPayload(),
            'input' => $this->input_schema,
            'output' => $this->output_schema,
            'version' => $this->version,
            // Per-environment client-side implementation status reported via the SDK.
            'implementations' => $this->whenLoaded('implementations', fn () => $this->implementations
                ->map(fn (ToolImplementation $implementation): array => [
                    'env' => $implementation->environment->label(),
                    'status' => $implementation->status->value,
                    'handler' => $implementation->handler_name,
                    'version' => $implementation->implemented_version,
                    'language' => $implementation->language?->label(),
                    'lastValidated' => $implementation->last_validated_at?->diffForHumans(),
                ])
                ->values()
                ->all()),
        ];
    }

    /**
     * Format the max payload (KB) for display, matching the fixture.
     */
    private function formattedPayload(): string
    {
        if ($this->max_payload_kb >= 1024 && $this->max_payload_kb % 1024 === 0) {
            return ($this->max_payload_kb / 1024).' MB';
        }

        return $this->max_payload_kb.' KB';
    }
}
