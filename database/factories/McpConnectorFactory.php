<?php

namespace Database\Factories;

use App\Enums\Environment;
use App\Enums\McpConnectorStatus;
use App\Enums\RemoteAuthType;
use App\Enums\Sensitivity;
use App\Models\Application;
use App\Models\McpConnector;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<McpConnector>
 */
class McpConnectorFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<McpConnector>
     */
    protected $model = McpConnector::class;

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
            'transport' => 'http',
            'server_url' => 'https://'.fake()->domainName().'/mcp',
            'auth_type' => RemoteAuthType::None,
            'auth_credential' => null,
            'auth_header' => null,
            'sensitivity' => Sensitivity::Internal,
            'requires_approval' => false,
            'status' => McpConnectorStatus::Active,
            'environments' => array_map(fn (Environment $environment): string => $environment->value, Environment::cases()),
            'capabilities' => null,
            'timeout_seconds' => 20,
            'last_discovered_at' => null,
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the connector is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => McpConnectorStatus::Disabled,
        ]);
    }

    /**
     * Indicate that the connector requires approval before production use.
     */
    public function requiresApproval(): static
    {
        return $this->state(fn (array $attributes): array => [
            'requires_approval' => true,
        ]);
    }

    /**
     * Authenticate the connector with a bearer token.
     */
    public function withBearer(string $token = 'mcp-secret-token'): static
    {
        return $this->state(fn (array $attributes): array => [
            'auth_type' => RemoteAuthType::Bearer,
            'auth_credential' => $token,
        ]);
    }

    /**
     * Seed the connector with discovered capabilities (remote tool descriptors).
     *
     * @param  array<int, array<string, mixed>>  $capabilities
     */
    public function withCapabilities(array $capabilities): static
    {
        return $this->state(fn (array $attributes): array => [
            'capabilities' => $capabilities,
            'last_discovered_at' => now(),
        ]);
    }

    /**
     * Restrict the connector to a specific set of environments.
     *
     * @param  array<int, Environment>  $environments
     */
    public function inEnvironments(array $environments): static
    {
        return $this->state(fn (array $attributes): array => [
            'environments' => array_map(fn (Environment $environment): string => $environment->value, $environments),
        ]);
    }
}
