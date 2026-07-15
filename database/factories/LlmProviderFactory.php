<?php

namespace Database\Factories;

use App\Enums\Environment;
use App\Enums\LlmStatus;
use App\Enums\LlmVerificationOutcome;
use App\Enums\Sensitivity;
use App\Models\LlmProvider;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LlmProvider>
 */
class LlmProviderFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<LlmProvider>
     */
    protected $model = LlmProvider::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $token = fake()->unique()->slug(2);

        return [
            'team_id' => Team::factory(),
            'slug' => $token,
            'name' => Str::headline($token),
            'code' => 'provider/'.$token,
            'provider' => fake()->randomElement(['Azure OpenAI', 'AWS Bedrock', 'Google Vertex AI', 'Milaha On-Prem GPU']),
            'context_window' => fake()->randomElement(['16K', '128K', '200K', '1M']),
            'input_cost' => fake()->randomFloat(2, 0, 5),
            'output_cost' => fake()->randomFloat(2, 0, 15),
            'sensitivity' => Sensitivity::Restricted,
            'environments' => [Environment::Production->value, Environment::Staging->value, Environment::Development->value],
            'status' => LlmStatus::Approved,
            'platform_owned' => true,
            'usage_pct' => fake()->numberBetween(0, 40),
            'runs_count' => fake()->numberBetween(0, 7000),
            'note' => fake()->sentence(),
            'verification_status' => LlmVerificationOutcome::Ok,
            'verification_message' => 'The provider returned a response.',
            'verified_at' => now(),
            'verification_checked_at' => now(),
        ];
    }

    /**
     * Indicate that the model is deprecated.
     */
    public function deprecated(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LlmStatus::Deprecated,
        ]);
    }

    /**
     * Indicate that the model is an unpublished draft awaiting verification.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => LlmStatus::Draft,
            'verification_status' => null,
            'verification_message' => null,
            'verified_at' => null,
            'verification_checked_at' => null,
        ]);
    }
}
