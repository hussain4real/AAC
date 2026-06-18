<?php

namespace Database\Factories;

use App\Enums\CredentialStatus;
use App\Enums\Environment;
use App\Models\Application;
use App\Models\Credential;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\ClientRepository;

/**
 * @extends Factory<Credential>
 */
class CredentialFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Credential>
     */
    protected $model = Credential::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $secret = Credential::generateSecret();

        return [
            'application_id' => Application::factory(),
            'environment' => Environment::Production,
            'label' => 'Production credentials',
            'client_id' => Credential::generateClientId(),
            'secret_hash' => Hash::make($secret),
            'last_four' => substr($secret, -4),
            'status' => CredentialStatus::Active,
            'last_used_at' => fake()->dateTimeBetween('-1 day', 'now'),
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the credential has been revoked.
     */
    public function revoked(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CredentialStatus::Revoked,
            'revoked_at' => now(),
        ]);
    }

    /**
     * Back the credential with a real Passport client_credentials client so it
     * can be used to authenticate SDK/runtime API requests in tests.
     */
    public function withOauthClient(): static
    {
        return $this->afterMaking(function (Credential $credential): void {
            $client = app(ClientRepository::class)->createClientCredentialsGrantClient('Test SDK client');

            $credential->client_id = $client->getKey();
            $credential->oauth_client_id = $client->getKey();
            $credential->fillSecret((string) $client->plainSecret);
        });
    }
}
