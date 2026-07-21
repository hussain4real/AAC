<?php

namespace Database\Factories;

use App\Enums\SsoConnectionStatus;
use App\Enums\SsoProvider;
use App\Enums\TeamRole;
use App\Models\SsoConnection;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SsoConnection>
 */
class SsoConnectionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<SsoConnection>
     */
    protected $model = SsoConnection::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' SSO';

        return [
            'team_id' => Team::factory(),
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999),
            'name' => $name,
            'provider' => SsoProvider::Oidc,
            'issuer' => 'https://idp.example.com',
            'authorize_url' => 'https://idp.example.com/authorize',
            'token_url' => 'https://idp.example.com/token',
            'userinfo_url' => 'https://idp.example.com/userinfo',
            'jwks_url' => 'https://idp.example.com/.well-known/jwks.json',
            'client_id' => 'client-'.fake()->unique()->lexify('????????'),
            'client_secret' => 'secret-'.Str::random(24),
            'scopes' => 'openid profile email groups',
            'email_claim' => 'email',
            'name_claim' => 'name',
            'groups_claim' => 'groups',
            'allowed_domains' => ['corp.com'],
            'default_team_role' => TeamRole::Member,
            'group_role_mappings' => [],
            'auto_provision' => true,
            'status' => SsoConnectionStatus::Active,
            'tested_at' => now(),
            'approved_at' => now(),
            'approved_by' => User::factory(),
            'created_by' => null,
        ];
    }

    /**
     * Indicate the connection is disabled.
     */
    public function disabled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SsoConnectionStatus::Disabled,
        ]);
    }

    /**
     * Indicate the connection has not completed test and approval.
     */
    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => SsoConnectionStatus::Draft,
            'tested_at' => null,
            'approved_at' => null,
            'approved_by' => null,
        ]);
    }

    /**
     * Set the group → role mappings.
     *
     * @param  array<int, array<string, mixed>>  $mappings
     */
    public function withMappings(array $mappings): static
    {
        return $this->state(fn (array $attributes): array => [
            'group_role_mappings' => $mappings,
        ]);
    }
}
