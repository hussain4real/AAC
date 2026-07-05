<?php

namespace Database\Factories;

use App\Enums\Environment;
use App\Enums\WebhookEndpointStatus;
use App\Models\Application;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookEndpoint>
 */
class WebhookEndpointFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<WebhookEndpoint>
     */
    protected $model = WebhookEndpoint::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $secret = WebhookEndpoint::generateSecret();

        return [
            'application_id' => Application::factory(),
            'environment' => Environment::Production,
            'url' => 'https://'.fake()->domainName().'/webhooks/maacc',
            'secret' => $secret,
            'last_four' => substr($secret, -6),
            'events' => ['*'],
            'description' => fake()->sentence(),
            'status' => WebhookEndpointStatus::Active,
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the endpoint is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => WebhookEndpointStatus::Disabled,
        ]);
    }

    /**
     * Subscribe the endpoint to a specific set of event types.
     *
     * @param  array<int, string>  $events
     */
    public function forEvents(array $events): static
    {
        return $this->state(fn (array $attributes): array => [
            'events' => $events,
        ]);
    }
}
