<?php

namespace Database\Factories;

use App\Models\EvaluationDataset;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EvaluationDataset>
 */
class EvaluationDatasetFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<EvaluationDataset>
     */
    protected $model = EvaluationDataset::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'project_id' => null,
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'created_by' => null,
        ];
    }
}
