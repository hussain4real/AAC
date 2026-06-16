<?php

namespace App\Http\Resources\Maac;

use App\Models\ToolCall;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a ToolCall for the run detail/trace view.
 *
 * @mixin ToolCall
 */
class ToolCallResource extends JsonResource
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
            'toolName' => $this->tool_name,
            'status' => $this->status->label(),
            'arguments' => $this->arguments ?? [],
            'result' => $this->result,
            'execMode' => $this->execution_mode?->value,
            'sequence' => $this->sequence,
        ];
    }
}
