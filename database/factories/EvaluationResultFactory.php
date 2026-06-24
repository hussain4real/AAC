<?php

namespace Database\Factories;

use App\Enums\EvaluationCaseKind;
use App\Models\Evaluation;
use App\Models\EvaluationResult;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationResult>
 */
class EvaluationResultFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<EvaluationResult>
     */
    protected $model = EvaluationResult::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'evaluation_id' => Evaluation::factory(),
            'evaluation_case_id' => null,
            'agent_run_id' => null,
            'case_name' => fake()->sentence(3),
            'kind' => EvaluationCaseKind::NoTool,
            'passed' => true,
            'checks' => [
                ['type' => 'correctness', 'passed' => true, 'detail' => 'Answer contained the expected text.'],
            ],
            'citations' => null,
            'cost' => fake()->randomFloat(6, 0, 0.05),
            'latency_ms' => fake()->numberBetween(50, 2000),
            'output' => fake()->sentence(),
            'failure_reason' => null,
        ];
    }

    /**
     * A failed result.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'passed' => false,
            'checks' => [
                ['type' => 'correctness', 'passed' => false, 'detail' => 'Answer missing expected text.'],
            ],
            'failure_reason' => 'assertion_failed',
        ]);
    }
}
