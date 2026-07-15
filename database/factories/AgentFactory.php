<?php

namespace Database\Factories;

use App\Enums\AgentStatus;
use App\Enums\Sensitivity;
use App\Models\Agent;
use App\Models\AgentVersion;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Support\Governance\AgentReadinessGate;
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
     * Keep ordinary test agents aligned with the production project/model
     * invariant. Adversarial tests can still corrupt the persisted tuple after
     * creation to prove fail-closed behavior.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (Agent $agent): void {
            $project = Project::query()->with('application.team')->find($agent->project_id);
            $provider = LlmProvider::query()->find($agent->llm_provider_id);

            if (! $project instanceof Project || ! $provider instanceof LlmProvider) {
                return;
            }

            if ((string) $provider->team_id !== (string) $project->application->team_id) {
                $provider = LlmProvider::factory()->for($project->application->team)->create();
                $agent->llm_provider_id = $provider->id;
            }

            $project->llmProviders()->syncWithoutDetaching([$provider->id]);
        });
    }

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
            'sensitivity' => Sensitivity::Internal,
            'requires_runtime_approval' => false,
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
        return $this
            ->state(fn (array $attributes): array => [
                'status' => AgentStatus::Published,
                'published_at' => now(),
            ])
            ->afterCreating(function (Agent $agent): void {
                $version = AgentVersion::factory()->for($agent)->published()->create([
                    'version' => $agent->version,
                    'system_prompt' => $agent->system_prompt,
                    'llm_provider_id' => $agent->llm_provider_id,
                    'temperature' => $agent->temperature,
                    'max_tokens' => $agent->max_tokens,
                    'settings' => [
                        'temperature' => $agent->temperature,
                        'max_tokens' => $agent->max_tokens,
                        'configuration_hash' => app(AgentReadinessGate::class)->configurationHash($agent),
                    ],
                    'published_at' => $agent->published_at,
                ]);

                $agent->update(['current_version_id' => $version->id]);
            });
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
