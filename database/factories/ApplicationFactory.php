<?php

namespace Database\Factories;

use App\Enums\AppStatus;
use App\Enums\Environment;
use App\Models\Application;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Application>
 */
class ApplicationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Application>
     */
    protected $model = Application::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'team_id' => Team::factory(),
            'slug' => fake()->unique()->slug(2),
            'code' => Str::slug($name),
            'name' => $name,
            'department' => fake()->randomElement(['Finance', 'Procurement', 'Maritime & Logistics', 'Customer Experience', 'Marine & Technical Services']),
            'owner_name' => fake()->name(),
            'owner_email' => fake()->unique()->safeEmail(),
            'environment' => fake()->randomElement(Environment::cases()),
            'status' => AppStatus::Active,
            'stack' => fake()->randomElement(['Laravel · PHP 8.3', 'Spring Boot · Java 21', 'Node.js · NestJS', 'Django · Python 3.12', '.NET 8 · C#']),
            'description' => fake()->sentence(),
            'region' => 'Qatar — Doha DC',
            'last_connected_at' => fake()->dateTimeBetween('-3 days', 'now'),
            'projects_count' => fake()->numberBetween(1, 3),
            'agents_count' => fake()->numberBetween(1, 4),
            'tools_required' => fake()->numberBetween(1, 6),
            'tools_implemented' => fake()->numberBetween(0, 5),
        ];
    }

    /**
     * Indicate that the application is suspended.
     */
    public function suspended(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AppStatus::Suspended,
        ]);
    }

    /**
     * Indicate that the application is archived.
     */
    public function archived(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AppStatus::Archived,
        ]);
    }
}
