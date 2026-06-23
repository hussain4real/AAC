<?php

namespace App\Support\Runtime\Mcp;

use App\Enums\Environment;
use App\Models\McpConnector;
use App\Models\ToolContract;
use App\Support\Runtime\ToolExecutionException;
use Laravel\Mcp\Client\Exceptions\AuthorizationRequiredException;
use Laravel\Mcp\Client\Schema\ToolResult;
use Laravel\Mcp\Exceptions\JsonRpcException;
use Throwable;

/**
 * Executes an MCP-backed tool by invoking the mapped remote tool on a registered
 * connector through the Laravel MCP client. It enforces connector availability,
 * normalizes the MCP tool result into a JSON object (validated against the tool's
 * output schema by the caller), and translates every failure mode into a
 * controlled {@see ToolExecutionException}.
 */
class McpToolExecutor
{
    public function __construct(private readonly McpConnectorClientFactory $factory) {}

    /**
     * Execute the connector tool against the model-supplied arguments.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws ToolExecutionException
     */
    public function execute(ToolContract $tool, ?Environment $environment, array $arguments): array
    {
        $connector = $tool->mcpConnector;
        $remoteTool = (string) $tool->mcp_tool_name;

        if (! $connector instanceof McpConnector || $remoteTool === '') {
            throw ToolExecutionException::connectorMisconfigured(
                "The tool [{$tool->slug}] is not mapped to a connector and remote tool name.",
            );
        }

        $envValue = $environment?->value;

        if ($envValue === null || ! $connector->isAvailableIn($envValue)) {
            throw ToolExecutionException::connectorUnavailable(
                "The connector [{$connector->slug}] is disabled or not available in the [".($envValue ?? 'unknown').'] environment.',
            );
        }

        return $this->normalize($this->call($connector, $remoteTool, $arguments), $remoteTool);
    }

    /**
     * Call the remote tool, mapping client/protocol exceptions to controlled
     * failures.
     *
     * @param  array<string, mixed>  $arguments
     *
     * @throws ToolExecutionException
     */
    private function call(McpConnector $connector, string $remoteTool, array $arguments): ToolResult
    {
        $client = $this->factory->make($connector);

        try {
            return $client->callTool($remoteTool, $arguments);
        } catch (AuthorizationRequiredException $exception) {
            throw ToolExecutionException::connectorUnauthorized($exception->getMessage());
        } catch (JsonRpcException $exception) {
            throw ToolExecutionException::connectorFailed($exception->getMessage());
        } catch (Throwable $exception) {
            throw ToolExecutionException::connectorUnreachable($exception->getMessage());
        } finally {
            $client->disconnect();
        }
    }

    /**
     * Convert the MCP tool result into a JSON object for schema validation,
     * preferring structured content and falling back to text content.
     *
     * @return array<string, mixed>
     *
     * @throws ToolExecutionException
     */
    private function normalize(ToolResult $result, string $remoteTool): array
    {
        if ($result->isError) {
            $detail = $result->text();

            throw ToolExecutionException::connectorFailed(
                $detail !== '' ? $detail : "the remote tool [{$remoteTool}] reported an error",
            );
        }

        if (is_array($result->structuredContent)) {
            return $result->structuredContent;
        }

        $text = $result->text();

        if ($text === '') {
            throw ToolExecutionException::connectorInvalidOutput(
                "The connector returned no content for the tool [{$remoteTool}].",
            );
        }

        return ['result' => $text];
    }
}
