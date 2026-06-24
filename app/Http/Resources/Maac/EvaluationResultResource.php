<?php

namespace App\Http\Resources\Maac;

use App\Models\EvaluationResult;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes a per-case evaluation result for the console: the verdict, the
 * per-check breakdown, the citations the run surfaced, and its cost/latency.
 *
 * @mixin EvaluationResult
 */
class EvaluationResultResource extends JsonResource
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
            'caseName' => $this->case_name,
            'kind' => $this->kind->value,
            'kindLabel' => $this->kind->label(),
            'passed' => $this->passed,
            'checks' => $this->checks,
            'citations' => $this->citations ?? [],
            'cost' => $this->cost,
            'latencyMs' => $this->latency_ms,
            'output' => $this->output,
            'failureReason' => $this->failure_reason,
            'runSlug' => $this->whenLoaded('run', fn () => $this->run?->slug),
        ];
    }
}
