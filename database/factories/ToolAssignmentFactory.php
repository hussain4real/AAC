<?php

namespace Database\Factories;

use App\Enums\ToolScope;
use App\Models\Agent;
use App\Models\Project;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolAssignment>
 */
class ToolAssignmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ToolAssignment>
     */
    protected $model = ToolAssignment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tool_contract_id' => ToolContract::factory(),
            'scope' => ToolScope::Global,
            'project_id' => null,
            'agent_id' => null,
            'environment' => null,
        ];
    }

    /**
     * Assign the tool at project scope.
     */
    public function forProject(Project $project): static
    {
        return $this->state(fn (array $attributes): array => [
            'scope' => ToolScope::Project,
            'project_id' => $project->id,
            'agent_id' => null,
        ]);
    }

    /**
     * Assign the tool at agent scope.
     */
    public function forAgent(Agent $agent): static
    {
        return $this->state(fn (array $attributes): array => [
            'scope' => ToolScope::Agent,
            'agent_id' => $agent->id,
            'project_id' => null,
        ]);
    }
}
