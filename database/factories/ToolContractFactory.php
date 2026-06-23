<?php

namespace Database\Factories;

use App\Enums\ExecMode;
use App\Enums\HttpMethod;
use App\Enums\ImplStatus;
use App\Enums\RemoteAuthType;
use App\Enums\Sensitivity;
use App\Enums\ToolScope;
use App\Models\Application;
use App\Models\McpConnector;
use App\Models\Team;
use App\Models\ToolContract;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ToolContract>
 */
class ToolContractFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<ToolContract>
     */
    protected $model = ToolContract::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::camel(fake()->unique()->slug(2));

        return [
            'team_id' => Team::factory(),
            'application_id' => Application::factory(),
            'slug' => fake()->unique()->slug(2),
            'name' => $name,
            'description' => fake()->sentence(),
            'scope' => ToolScope::Project,
            'execution_mode' => ExecMode::Client,
            'sensitivity' => Sensitivity::Internal,
            'requires_approval' => false,
            'status' => 'Active',
            'implementation_status' => ImplStatus::Implemented,
            'timeout_seconds' => 15,
            'max_payload_kb' => 256,
            'input_schema' => ['query' => 'string', 'limit' => 'number?'],
            'output_schema' => ['results' => 'array', 'total' => 'number'],
            'version' => '1.0.0',
        ];
    }

    /**
     * Indicate that the tool is a global/platform tool.
     */
    public function global(): static
    {
        return $this->state(fn (array $attributes): array => [
            'application_id' => null,
            'scope' => ToolScope::Global,
            'execution_mode' => ExecMode::Hosted,
            'implementation_status' => ImplStatus::Ready,
        ]);
    }

    /**
     * Indicate that the tool is client-side and requires approval.
     */
    public function requiresApproval(): static
    {
        return $this->state(fn (array $attributes): array => [
            'requires_approval' => true,
            'execution_mode' => ExecMode::Client,
        ]);
    }

    /**
     * Indicate that the tool is a remote HTTP tool.
     *
     * @param  array<string, mixed>  $config
     */
    public function remoteHttp(array $config = []): static
    {
        return $this->state(fn (array $attributes): array => [
            'execution_mode' => ExecMode::Http,
            'implementation_status' => ImplStatus::Ready,
            'http_config' => array_merge([
                'method' => HttpMethod::Post->value,
                'endpoint' => 'https://tools.example.com/v1/lookup',
                'auth' => ['type' => RemoteAuthType::None->value],
                'retry' => ['max_attempts' => 1, 'backoff_ms' => 0],
            ], $config),
        ]);
    }

    /**
     * Indicate that the tool is backed by an MCP connector.
     */
    public function connector(?McpConnector $connector = null, string $remoteTool = 'lookup'): static
    {
        return $this->state(fn (array $attributes): array => [
            'execution_mode' => ExecMode::Connector,
            'implementation_status' => ImplStatus::Ready,
            'mcp_connector_id' => $connector instanceof McpConnector ? $connector->id : McpConnector::factory(),
            'mcp_tool_name' => $remoteTool,
        ]);
    }
}
