<?php

namespace Database\Factories;

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\LlmProvider;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Agent>
 */
class AgentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Agent>
     */
    protected $model = Agent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::headline(fake()->unique()->slug(2)).' Agent';

        return [
            'project_id' => Project::factory(),
            'llm_provider_id' => LlmProvider::factory(),
            'current_version_id' => null,
            'slug' => fake()->unique()->slug(2),
            'agent_slug' => fake()->unique()->slug(2),
            'name' => $name,
            'version' => 'v1',
            'status' => AgentStatus::Draft,
            'system_prompt' => fake()->paragraph(),
            'temperature' => fake()->randomFloat(2, 0, 1),
            'max_tokens' => fake()->randomElement([1200, 1400, 1500, 1800]),
            'description' => fake()->sentence(),
            'success_rate' => fake()->randomFloat(1, 90, 100),
            'runs_7d' => fake()->numberBetween(0, 1500),
            'last_run_at' => fake()->dateTimeBetween('-1 day', 'now'),
            'published_at' => null,
        ];
    }

    /**
     * Indicate that the agent is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AgentStatus::Published,
            'published_at' => now(),
        ]);
    }

    /**
     * Indicate that the agent is in testing.
     */
    public function testing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AgentStatus::Testing,
        ]);
    }

    /**
     * Indicate that the agent is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AgentStatus::Disabled,
        ]);
    }
}
