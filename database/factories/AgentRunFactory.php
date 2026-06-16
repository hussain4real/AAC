<?php

namespace Database\Factories;

use App\Enums\RunStatus;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\LlmProvider;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentRun>
 */
class AgentRunFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<AgentRun>
     */
    protected $model = AgentRun::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'project_id' => Project::factory(),
            'application_id' => Application::factory(),
            'llm_provider_id' => LlmProvider::factory(),
            'slug' => 'run_'.fake()->unique()->bothify('######'),
            'caller' => fake()->userName(),
            'status' => RunStatus::Completed,
            'tokens_in' => fake()->numberBetween(500, 5000),
            'tokens_out' => fake()->numberBetween(0, 1500),
            'cost' => fake()->randomFloat(6, 0, 0.05),
            'latency_ms' => fake()->numberBetween(1000, 8000),
            'tools' => [],
            'input' => fake()->sentence(),
            'error' => null,
            'started_at' => fake()->dateTimeBetween('-1 day', 'now'),
            'completed_at' => fake()->dateTimeBetween('-1 day', 'now'),
        ];
    }

    /**
     * Indicate that the run failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RunStatus::Failed,
            'tokens_out' => 0,
            'error' => fake()->sentence(),
            'completed_at' => null,
        ]);
    }

    /**
     * Indicate that the run is waiting for a client-side tool result.
     */
    public function waitingForClient(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => RunStatus::WaitingForClient,
            'tokens_out' => 0,
            'latency_ms' => null,
            'completed_at' => null,
        ]);
    }
}
