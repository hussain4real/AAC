<?php

namespace App\Http\Resources\Maac;

use App\Models\Evaluation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Serializes an evaluation run for the console: the agent/version/model/prompt
 * snapshot, the rolled-up outcome metrics (used for cross-version comparison),
 * the promotion-gate flag, and — when loaded — its per-case results.
 *
 * @mixin Evaluation
 */
class EvaluationResource extends JsonResource
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
            'label' => $this->label,
            'status' => $this->status->value,
            'statusLabel' => $this->status->label(),
            'isRequired' => $this->is_required,
            'environment' => ucfirst($this->environment->value),
            'datasetId' => $this->evaluation_dataset_id,
            'datasetName' => $this->whenLoaded('dataset', fn () => $this->dataset->name),
            'agentId' => $this->agent_id,
            'agentSlug' => $this->whenLoaded('agent', fn () => $this->agent->slug),
            'agentName' => $this->whenLoaded('agent', fn () => $this->agent->name),
            'agentVersion' => $this->agent_version,
            'modelCode' => $this->model_code,
            'promptFingerprint' => $this->prompt_fingerprint,
            'casesTotal' => $this->cases_total,
            'casesPassed' => $this->cases_passed,
            'passRate' => $this->pass_rate,
            'totalCost' => $this->total_cost,
            'avgLatencyMs' => $this->avg_latency_ms,
            'correctnessRate' => $this->correctness_rate,
            'safetyRate' => $this->safety_rate,
            'citationRate' => $this->citation_rate,
            'completedAt' => $this->completed_at?->diffForHumans(),
            'createdAt' => $this->created_at?->format('j M Y, H:i'),
            'results' => $this->whenLoaded('results', fn () => EvaluationResultResource::collection($this->results)->resolve(), []),
        ];
    }
}
