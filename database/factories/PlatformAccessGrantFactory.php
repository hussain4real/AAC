<?php

namespace Database\Factories;

use App\Enums\PlatformAccessKind;
use App\Enums\PlatformRole;
use App\Models\PlatformAccessGrant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformAccessGrant>
 */
class PlatformAccessGrantFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'role' => PlatformRole::Auditor->value,
            'kind' => PlatformAccessKind::Standard->value,
            'reason' => $this->faker->sentence(),
            'granted_by' => User::factory(),
            'expires_at' => null,
            'certified_at' => null,
            'revoked_at' => null,
        ];
    }

    /**
     * A time-boxed break-glass grant that has already elapsed.
     */
    public function expiredBreakGlass(): static
    {
        return $this->state(fn (): array => [
            'kind' => PlatformAccessKind::BreakGlass->value,
            'expires_at' => now()->subHour(),
        ]);
    }

    /**
     * A revoked grant.
     */
    public function revoked(): static
    {
        return $this->state(fn (): array => [
            'revoked_at' => now(),
            'revoked_by' => User::factory(),
        ]);
    }
}
