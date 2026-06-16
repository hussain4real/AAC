<?php

namespace Database\Factories;

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\AgentVersion;
use App\Models\LlmProvider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AgentVersion>
 */
class AgentVersionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<AgentVersion>
     */
    protected $model = AgentVersion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_id' => Agent::factory(),
            'version' => 'v1',
            'system_prompt' => fake()->paragraph(),
            'llm_provider_id' => LlmProvider::factory(),
            'temperature' => 0.2,
            'max_tokens' => 1500,
            'settings' => ['temperature' => 0.2, 'max_tokens' => 1500],
            'status' => AgentStatus::Draft,
            'published_at' => null,
            'published_by' => null,
            'notes' => null,
        ];
    }

    /**
     * Indicate that the version is published.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => AgentStatus::Published,
            'published_at' => now(),
        ]);
    }
}
