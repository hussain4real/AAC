<?php

namespace Database\Factories;

use App\Enums\Environment;
use App\Enums\KnowledgeSourceStatus;
use App\Enums\Sensitivity;
use App\Models\Application;
use App\Models\KnowledgeSource;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeSource>
 */
class KnowledgeSourceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<KnowledgeSource>
     */
    protected $model = KnowledgeSource::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'application_id' => Application::factory(),
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->sentence(),
            'status' => KnowledgeSourceStatus::Active,
            'sensitivity' => Sensitivity::Internal,
            'requires_approval' => false,
            'environments' => array_map(
                fn (Environment $environment): string => $environment->value,
                Environment::cases(),
            ),
            'document_count' => 0,
            'chunk_count' => 0,
            'last_indexed_at' => null,
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the source is still a draft awaiting ingestion.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => KnowledgeSourceStatus::Draft,
        ]);
    }

    /**
     * Indicate that the source is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => KnowledgeSourceStatus::Disabled,
        ]);
    }

    /**
     * Indicate that the source is sensitive and gated behind ingestion approval.
     */
    public function sensitive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sensitivity' => Sensitivity::Confidential,
            'requires_approval' => true,
            'status' => KnowledgeSourceStatus::Draft,
        ]);
    }

    /**
     * Restrict the source to the given environments.
     *
     * @param  array<int, Environment>  $environments
     */
    public function inEnvironments(array $environments): static
    {
        return $this->state(fn (array $attributes): array => [
            'environments' => array_map(
                fn (Environment $environment): string => $environment->value,
                $environments,
            ),
        ]);
    }
}
