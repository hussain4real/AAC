<?php

namespace Database\Factories;

use App\Enums\EvaluationCaseKind;
use App\Models\EvaluationCase;
use App\Models\EvaluationDataset;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationCase>
 */
class EvaluationCaseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<EvaluationCase>
     */
    protected $model = EvaluationCase::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'evaluation_dataset_id' => EvaluationDataset::factory(),
            'name' => fake()->sentence(3),
            'kind' => EvaluationCaseKind::NoTool,
            'input' => fake()->sentence(),
            'expectations' => [
                'expected_contains' => [],
                'expected_tool' => null,
                'forbidden_phrases' => [],
                'expects_citation' => false,
                'max_cost' => null,
                'max_latency_ms' => null,
            ],
            'tool_stubs' => null,
            'ordinal' => 0,
        ];
    }

    /**
     * A case expecting the agent to call a client-side tool, with a stub result.
     *
     * @param  array<string, mixed>  $stub
     */
    public function clientTool(string $tool, array $stub): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => EvaluationCaseKind::ClientTool,
            'expectations' => [...$attributes['expectations'], 'expected_tool' => $tool],
            'tool_stubs' => [$tool => $stub],
        ]);
    }

    /**
     * A case expecting a knowledge-retrieval (RAG) answer with a citation.
     */
    public function rag(string $tool): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => EvaluationCaseKind::Rag,
            'expectations' => [...$attributes['expectations'], 'expected_tool' => $tool, 'expects_citation' => true],
        ]);
    }

    /**
     * A safety case that forbids the given phrases from the answer.
     *
     * @param  array<int, string>  $phrases
     */
    public function forbids(array $phrases): static
    {
        return $this->state(fn (array $attributes): array => [
            'expectations' => [...$attributes['expectations'], 'forbidden_phrases' => $phrases],
        ]);
    }

    /**
     * A case asserting the answer contains the given substrings.
     *
     * @param  array<int, string>  $needles
     */
    public function expects(array $needles): static
    {
        return $this->state(fn (array $attributes): array => [
            'expectations' => [...$attributes['expectations'], 'expected_contains' => $needles],
        ]);
    }
}
