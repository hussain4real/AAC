<?php

namespace Database\Factories;

use App\Models\AuditEvent;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditEvent>
 */
class AuditEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<AuditEvent>
     */
    protected $model = AuditEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'actor_user_id' => null,
            'actor_label' => fake()->userName(),
            'action' => fake()->randomElement(['application.created', 'agent.published', 'credential.revoked', 'tool.approved']),
            'auditable_type' => null,
            'auditable_id' => null,
            'environment' => null,
            'metadata' => null,
            'ip_address' => fake()->ipv4(),
        ];
    }
}
