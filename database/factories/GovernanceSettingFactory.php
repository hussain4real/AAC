<?php

namespace Database\Factories;

use App\Models\GovernanceSetting;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GovernanceSetting>
 */
class GovernanceSettingFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<GovernanceSetting>
     */
    protected $model = GovernanceSetting::class;

    /**
     * Define the model's default state (relies on the model's default
     * attributes for retention/masking values).
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
        ];
    }
}
