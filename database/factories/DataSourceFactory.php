<?php

namespace Database\Factories;

use App\Enums\DataSourceStatus;
use App\Enums\DbConnectionType;
use App\Enums\Environment;
use App\Enums\Sensitivity;
use App\Models\Application;
use App\Models\DataSource;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DataSource>
 */
class DataSourceFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<DataSource>
     */
    protected $model = DataSource::class;

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
            'connection_type' => DbConnectionType::ReadReplica,
            // Defaults to the app's configured connection so a factory-built
            // source is queryable in tests against the seeded schema.
            'connection' => (string) config('database.default'),
            'driver' => 'sqlite',
            'vault_secret_id' => null,
            'status' => DataSourceStatus::Active,
            'sensitivity' => Sensitivity::Internal,
            'requires_approval' => false,
            'environments' => array_map(fn (Environment $environment): string => $environment->value, Environment::cases()),
            'allowed_relations' => ['reporting_metrics'],
            'max_rows' => 100,
            'statement_timeout_ms' => 5000,
            'max_result_kb' => 256,
            'data_refreshed_at' => now(),
            'staleness_threshold_minutes' => null,
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the source is a draft awaiting access approval.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DataSourceStatus::Draft,
        ]);
    }

    /**
     * Indicate that the source is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => DataSourceStatus::Disabled,
        ]);
    }

    /**
     * Indicate that the source requires approval before production use.
     */
    public function requiresApproval(): static
    {
        return $this->state(fn (array $attributes): array => [
            'requires_approval' => true,
        ]);
    }

    /**
     * Indicate that the source carries confidential data.
     */
    public function sensitive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'sensitivity' => Sensitivity::Confidential,
        ]);
    }

    /**
     * Restrict the source to a specific set of environments.
     *
     * @param  array<int, Environment>  $environments
     */
    public function inEnvironments(array $environments): static
    {
        return $this->state(fn (array $attributes): array => [
            'environments' => array_map(fn (Environment $environment): string => $environment->value, $environments),
        ]);
    }

    /**
     * Set the approved query surface (allowlisted relations) for the source.
     *
     * @param  array<int, string>  $relations
     */
    public function allowing(array $relations): static
    {
        return $this->state(fn (array $attributes): array => [
            'allowed_relations' => $relations,
        ]);
    }

    /**
     * Reference a specific (ops-provisioned) read-only connection by name.
     */
    public function onConnection(string $connection): static
    {
        return $this->state(fn (array $attributes): array => [
            'connection' => $connection,
        ]);
    }

    /**
     * Mark the source's data as last refreshed at the given time, with an
     * optional staleness threshold (minutes) for freshness enforcement.
     */
    public function refreshedAt(\DateTimeInterface $when, ?int $thresholdMinutes = null): static
    {
        return $this->state(fn (array $attributes): array => [
            'data_refreshed_at' => $when,
            'staleness_threshold_minutes' => $thresholdMinutes,
        ]);
    }
}
