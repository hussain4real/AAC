<?php

namespace Database\Factories;

use App\Enums\Environment;
use App\Enums\ProjectStatus;
use App\Models\Application;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Project>
     */
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'slug' => fake()->unique()->slug(2),
            'name' => Str::headline(fake()->unique()->slug(3)),
            'environment' => fake()->randomElement(Environment::cases()),
            'description' => fake()->sentence(),
            'business_owner' => fake()->name(),
            'technical_owner' => fake()->name(),
            'status' => ProjectStatus::Active,
            'agents_count' => fake()->numberBetween(1, 2),
            'tools_count' => fake()->numberBetween(1, 5),
            'runs_7d' => fake()->numberBetween(0, 2000),
        ];
    }

    /**
     * Indicate that the project is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ProjectStatus::Archived,
        ]);
    }
}
