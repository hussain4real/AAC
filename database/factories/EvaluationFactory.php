<?php

namespace Database\Factories;

use App\Enums\Environment;
use App\Enums\EvaluationStatus;
use App\Models\Agent;
use App\Models\Evaluation;
use App\Models\EvaluationDataset;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Evaluation>
 */
class EvaluationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Evaluation>
     */
    protected $model = Evaluation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'evaluation_dataset_id' => EvaluationDataset::factory(),
            'agent_id' => Agent::factory(),
            'agent_version_id' => null,
            'environment' => Environment::Production,
            'label' => fake()->sentence(3),
            'status' => EvaluationStatus::Pending,
            'is_required' => false,
            'agent_version' => 'v1',
            'model_code' => 'provider/'.fake()->slug(1),
            'prompt_fingerprint' => substr(hash('sha256', fake()->sentence()), 0, 16),
            'cases_total' => 0,
            'cases_passed' => 0,
            'pass_rate' => 0,
            'total_cost' => 0,
            'avg_latency_ms' => 0,
            'correctness_rate' => 0,
            'safety_rate' => 0,
            'citation_rate' => 0,
            'started_at' => null,
            'completed_at' => null,
            'created_by' => null,
        ];
    }

    /**
     * A passed evaluation (all required cases met their assertions).
     */
    public function passed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EvaluationStatus::Passed,
            'cases_total' => 4,
            'cases_passed' => 4,
            'pass_rate' => 100,
            'correctness_rate' => 100,
            'safety_rate' => 100,
            'citation_rate' => 100,
            'completed_at' => now(),
        ]);
    }

    /**
     * A failed evaluation (at least one case missed its assertions).
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => EvaluationStatus::Failed,
            'cases_total' => 4,
            'cases_passed' => 2,
            'pass_rate' => 50,
            'completed_at' => now(),
        ]);
    }

    /**
     * Flag the evaluation as a promotion-gating requirement.
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_required' => true,
        ]);
    }
}
