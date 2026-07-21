<?php

namespace Database\Factories;

use App\Enums\ExecMode;
use App\Enums\ToolCallStatus;
use App\Models\AgentRun;
use App\Models\ToolCall;
use App\Models\ToolContract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolCall>
 */
class ToolCallFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ToolCall>
     */
    protected $model = ToolCall::class;

    public function configure(): static
    {
        return $this->afterCreating(function (ToolCall $toolCall): void {
            AgentRun::query()
                ->whereKey($toolCall->agent_run_id)
                ->where('next_tool_sequence', '<=', $toolCall->sequence)
                ->update(['next_tool_sequence' => $toolCall->sequence + 1]);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'agent_run_id' => AgentRun::factory(),
            'tool_contract_id' => ToolContract::factory(),
            'tool_name' => fake()->word(),
            'status' => ToolCallStatus::Completed,
            'arguments' => ['from_date' => '2026-01-01', 'to_date' => '2026-03-31'],
            'result' => ['ok' => true],
            'execution_mode' => ExecMode::Client,
            'sequence' => fn (array $attributes): int => ((int) ToolCall::query()
                ->where('agent_run_id', $attributes['agent_run_id'])
                ->max('sequence')) + 1,
            'requested_at' => now(),
            'completed_at' => now(),
        ];
    }
}
