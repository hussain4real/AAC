<?php

namespace App\Http\Controllers\Maac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StoreEvaluationCaseRequest;
use App\Models\EvaluationCase;
use App\Models\EvaluationDataset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Console management of the cases within a golden dataset. Authorization follows
 * the parent dataset (managing cases is managing the dataset).
 */
class EvaluationCaseController extends Controller
{
    /**
     * Add a case to a dataset.
     */
    public function store(StoreEvaluationCaseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $dataset = EvaluationDataset::findOrFail((string) $data['evaluation_dataset_id']);

        Gate::authorize('update', $dataset);

        $dataset->cases()->create([
            'name' => $data['name'],
            'kind' => $data['kind'],
            'input' => $data['input'],
            'expectations' => $this->normalizeExpectations(is_array($data['expectations'] ?? null) ? $data['expectations'] : []),
            'tool_stubs' => $data['tool_stubs'] ?? null,
            'ordinal' => $data['ordinal'] ?? (((int) $dataset->cases()->max('ordinal')) + 1),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evaluation case added.']);

        return back();
    }

    /**
     * Remove a case from its dataset.
     */
    public function destroy(Request $request, string $currentTeam, EvaluationCase $evaluationCase): RedirectResponse
    {
        Gate::authorize('update', $evaluationCase->dataset);

        $evaluationCase->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Evaluation case removed.']);

        return back();
    }

    /**
     * Normalize the submitted expectations into the full assertion shape with
     * sane defaults, dropping empty list entries.
     *
     * @param  array<string, mixed>  $expectations
     * @return array<string, mixed>
     */
    private function normalizeExpectations(array $expectations): array
    {
        return [
            'expected_contains' => $this->stringList($expectations['expected_contains'] ?? []),
            'expected_tool' => $this->trimmedOrNull($expectations['expected_tool'] ?? null),
            'forbidden_phrases' => $this->stringList($expectations['forbidden_phrases'] ?? []),
            'expects_citation' => ($expectations['expects_citation'] ?? false) === true,
            'max_cost' => isset($expectations['max_cost']) && is_numeric($expectations['max_cost']) ? (float) $expectations['max_cost'] : null,
            'max_latency_ms' => isset($expectations['max_latency_ms']) && is_numeric($expectations['max_latency_ms']) ? (int) $expectations['max_latency_ms'] : null,
        ];
    }

    /**
     * Reduce a raw value to a list of trimmed, non-empty strings.
     *
     * @return array<int, string>
     */
    private function stringList(mixed $value): array
    {
        $list = [];

        foreach ((array) $value as $item) {
            if (is_string($item) && trim($item) !== '') {
                $list[] = trim($item);
            }
        }

        return $list;
    }

    /**
     * Trim a value to a non-empty string or null.
     */
    private function trimmedOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
