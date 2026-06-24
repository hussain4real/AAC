<?php

namespace App\Models;

use App\Enums\EvaluationCaseKind;
use Database\Factories\EvaluationCaseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A single case in a golden dataset: the input the agent is given, the workflow
 * it exercises, the assertions its run must satisfy (`expectations`), and any
 * stubbed tool results (`tool_stubs`) the evaluation feeds back when the run
 * pauses for a client-side tool.
 *
 * @property string $id
 * @property string $evaluation_dataset_id
 * @property string $name
 * @property EvaluationCaseKind $kind
 * @property string $input
 * @property array<string, mixed> $expectations
 * @property array<string, mixed>|null $tool_stubs
 * @property int $ordinal
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read EvaluationDataset $dataset
 */
#[Fillable(['evaluation_dataset_id', 'name', 'kind', 'input', 'expectations', 'tool_stubs', 'ordinal'])]
class EvaluationCase extends Model
{
    /** @use HasFactory<EvaluationCaseFactory> */
    use HasFactory, HasUuids;

    /**
     * Get the dataset this case belongs to.
     *
     * @return BelongsTo<EvaluationDataset, $this>
     */
    public function dataset(): BelongsTo
    {
        return $this->belongsTo(EvaluationDataset::class, 'evaluation_dataset_id');
    }

    /**
     * Get the stub result for a client-side tool, if the case provides one.
     *
     * @return array<string, mixed>|null
     */
    public function toolStub(string $tool): ?array
    {
        $stub = ($this->tool_stubs ?? [])[$tool] ?? null;

        return is_array($stub) ? $stub : null;
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
            'expectations' => 'array',
            'tool_stubs' => 'array',
            'ordinal' => 'integer',
        ];
    }
}
