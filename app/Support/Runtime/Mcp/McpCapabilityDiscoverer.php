<?php

namespace App\Support\Runtime\Mcp;

use App\Models\McpConnector;
use App\Support\Runtime\ToolExecutionException;
use Illuminate\Support\Facades\Date;
use Laravel\Mcp\Client\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Client\Primitives\Tool;
use Throwable;

/**
 * Discovers a connector's capabilities by listing the remote MCP server's tools
 * and persists a normalized descriptor set on the connector (name, title,
 * description, input schema). This backs the console's capability view and the
 * permission mapping from a remote tool to a MAAC tool contract.
 */
class McpCapabilityDiscoverer
{
    public function __construct(private readonly McpConnectorClientFactory $factory) {}

    /**
     * Discover and persist the connector's remote tool capabilities, returning
     * the normalized descriptors.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ToolExecutionException
     */
    public function discover(McpConnector $connector): array
    {
        $capabilities = $this->fetch($connector);

        $connector->forceFill([
            'capabilities' => $capabilities,
            'last_discovered_at' => Date::now(),
        ])->save();

        return $capabilities;
    }

    /**
     * List the connector's tools, mapping failures to controlled exceptions.
     *
     * @return array<int, array<string, mixed>>
     *
     * @throws ToolExecutionException
     */
    private function fetch(McpConnector $connector): array
    {
        $client = $this->factory->make($connector);

        try {
            $tools = $client->tools();
        } catch (AuthorizationRequiredException $exception) {
            throw ToolExecutionException::connectorUnauthorized($exception->getMessage());
        } catch (Throwable $exception) {
            throw ToolExecutionException::connectorUnreachable($exception->getMessage());
        } finally {
            $client->disconnect();
        }

        return $tools
            ->map(fn (Tool $tool): array => [
                'name' => $tool->name,
                'title' => $tool->title,
                'description' => $tool->description,
                'input_schema' => $tool->inputSchema,
            ])
            ->values()
            ->all();
    }
}
