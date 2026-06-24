<?php

namespace App\Models;

use App\Enums\EvaluationCaseKind;
use Database\Factories\EvaluationResultFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * The outcome of one evaluation case: the agent run it produced, whether it
 * passed, the per-check results, the citations the run surfaced, and its
 * cost/latency. The case name and kind are snapshotted so the result stays
 * readable if the source case is later edited or removed.
 *
 * @property string $id
 * @property string $evaluation_id
 * @property string|null $evaluation_case_id
 * @property string|null $agent_run_id
 * @property string $case_name
 * @property EvaluationCaseKind $kind
 * @property bool $passed
 * @property array<int, array<string, mixed>> $checks
 * @property array<int, array<string, mixed>>|null $citations
 * @property float $cost
 * @property int $latency_ms
 * @property string|null $output
 * @property string|null $failure_reason
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Evaluation $evaluation
 * @property-read EvaluationCase|null $case
 * @property-read AgentRun|null $run
 */
#[Fillable(['evaluation_id', 'evaluation_case_id', 'agent_run_id', 'case_name', 'kind', 'passed', 'checks', 'citations', 'cost', 'latency_ms', 'output', 'failure_reason'])]
class EvaluationResult extends Model
{
    /** @use HasFactory<EvaluationResultFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the evaluation this result belongs to.
     *
     * @return BelongsTo<Evaluation, $this>
     */
    public function evaluation(): BelongsTo
    {
        return $this->belongsTo(Evaluation::class);
    }

    /**
     * Get the source case this result was produced from, if still present.
     *
     * @return BelongsTo<EvaluationCase, $this>
     */
    public function case(): BelongsTo
    {
        return $this->belongsTo(EvaluationCase::class, 'evaluation_case_id');
    }

    /**
     * Get the agent run this result was produced from.
     *
     * @return BelongsTo<AgentRun, $this>
     */
    public function run(): BelongsTo
    {
        return $this->belongsTo(AgentRun::class, 'agent_run_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => EvaluationCaseKind::class,
            'passed' => 'boolean',
            'checks' => 'array',
            'citations' => 'array',
            'cost' => 'float',
            'latency_ms' => 'integer',
        ];
    }
}
