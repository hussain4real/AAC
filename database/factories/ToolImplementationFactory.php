<?php

namespace Database\Factories;

use App\Enums\Environment;
use App\Enums\ImplStatus;
use App\Models\Application;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ToolImplementation>
 */
class ToolImplementationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ToolImplementation>
     */
    protected $model = ToolImplementation::class;

    /**
     * Keep implementation compatibility metadata aligned with its contract.
     */
    public function configure(): static
    {
        return $this->afterMaking(function (ToolImplementation $implementation): void {
            $tool = ToolContract::query()->find($implementation->tool_contract_id);

            if (! $tool instanceof ToolContract) {
                return;
            }

            $implementation->implemented_version ??= $tool->version;
            $implementation->schema_fingerprint ??= $tool->schemaFingerprint();
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
            'tool_contract_id' => ToolContract::factory(),
            'application_id' => Application::factory(),
            'environment' => Environment::Production,
            'status' => ImplStatus::Implemented,
            'handler_name' => fake()->word(),
            'implemented_version' => '1.0.0',
            'schema_fingerprint' => null,
            'last_validated_at' => fake()->dateTimeBetween('-1 day', 'now'),
        ];
    }

    /**
     * Indicate that the implementation is still required.
     */
    public function required(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => ImplStatus::Required,
            'handler_name' => null,
            'implemented_version' => null,
            'last_validated_at' => null,
        ]);
    }
}
