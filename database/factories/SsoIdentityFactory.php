<?php

namespace Database\Factories;

use App\Models\SsoConnection;
use App\Models\SsoIdentity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SsoIdentity>
 */
class SsoIdentityFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<SsoIdentity>
     */
    protected $model = SsoIdentity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sso_connection_id' => SsoConnection::factory(),
            'user_id' => User::factory(),
            'subject' => 'sub-'.fake()->unique()->uuid(),
            'email' => fake()->unique()->safeEmail(),
            'raw_claims' => [],
            'managed_project_ids' => [],
            'last_login_at' => now(),
        ];
    }
}
