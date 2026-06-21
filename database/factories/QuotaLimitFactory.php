<?php

namespace Database\Factories;

use App\Enums\QuotaScope;
use App\Models\QuotaLimit;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuotaLimit>
 */
class QuotaLimitFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<QuotaLimit>
     */
    protected $model = QuotaLimit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'scope' => QuotaScope::Platform,
            'subject_id' => null,
            'environment' => null,
            'max_runs_per_day' => fake()->numberBetween(100, 5000),
            'max_tokens_per_day' => null,
            'enabled' => true,
        ];
    }
}
