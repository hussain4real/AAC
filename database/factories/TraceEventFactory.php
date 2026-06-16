<?php

namespace Database\Factories;

use App\Enums\TraceEventType;
use App\Models\AgentRun;
use App\Models\TraceEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TraceEvent>
 */
class TraceEventFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<TraceEvent>
     */
    protected $model = TraceEvent::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_run_id' => AgentRun::factory(),
            'type' => TraceEventType::RunRequested,
            'message' => fake()->sentence(),
            'data' => null,
            'sequence' => 0,
            'occurred_at' => now(),
        ];
    }
}
