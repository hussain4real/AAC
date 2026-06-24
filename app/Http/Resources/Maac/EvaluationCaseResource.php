<?php

namespace App\Http\Resources\Maac;

use App\Models\EvaluationCase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a golden dataset case for the console: the workflow it exercises,
 * the input, the assertions it declares, and any stubbed client-tool results.
 *
 * @mixin EvaluationCase
 */
class EvaluationCaseResource extends JsonResource
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
            'name' => $this->name,
            'kind' => $this->kind->value,
            'kindLabel' => $this->kind->label(),
            'input' => $this->input,
            'expectations' => $this->expectations,
            'toolStubs' => $this->tool_stubs,
            'ordinal' => $this->ordinal,
        ];
    }
}
