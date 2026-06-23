<?php

namespace Tests\Support\Mcp;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * A scripted MCP server for tests: it produces an Http::fake() handler that
 * answers the JSON-RPC handshake (initialize + notifications/initialized) and
 * serves tools/list and tools/call responses, so the real Laravel MCP client can
 * be driven end-to-end without a live server.
 */
class FakeMcpServer
{
    /** @var array<int, array<string, mixed>> */
    private array $tools = [];

    /** @var array<string, array<string, mixed>> */
    private array $results = [];

    public static function make(): self
    {
        return new self;
    }

    /**
     * Register a tool that tools/list will advertise.
     *
     * @param  array<string, mixed>  $inputSchema
     */
    public function withTool(string $name, array $inputSchema = [], ?string $description = null): self
    {
        $this->tools[] = [
            'name' => $name,
            'title' => $description ?? $name,
            'description' => $description ?? "The {$name} tool.",
            'inputSchema' => $inputSchema === [] ? ['type' => 'object'] : $inputSchema,
        ];

        return $this;
    }

    /**
     * Script a successful tools/call result for a tool (structured + text).
     *
     * @param  array<string, mixed>  $structured
     */
    public function returns(string $tool, array $structured, string $text = 'ok'): self
    {
        $this->results[$tool] = [
            'content' => [['type' => 'text', 'text' => $text]],
            'isError' => false,
            'structuredContent' => $structured,
        ];

        return $this;
    }

    /**
     * Script a text-only tools/call result (no structured content).
     */
    public function returnsText(string $tool, string $text): self
    {
        $this->results[$tool] = [
            'content' => [['type' => 'text', 'text' => $text]],
            'isError' => false,
        ];

        return $this;
    }

    /**
     * Script a tool-level error result for a tool.
     */
    public function returnsError(string $tool, string $text = 'tool failed'): self
    {
        $this->results[$tool] = [
            'content' => [['type' => 'text', 'text' => $text]],
            'isError' => true,
        ];

        return $this;
    }

    /**
     * Script an empty (no content) result for a tool.
     */
    public function returnsEmpty(string $tool): self
    {
        $this->results[$tool] = [
            'content' => [],
            'isError' => false,
        ];

        return $this;
    }

    /**
     * Build the Http::fake() handler for the configured server.
     */
    public function handler(): Closure
    {
        return function (Request $request): Response|PromiseInterface {
            $payload = json_decode($request->body(), true);
            $payload = is_array($payload) ? $payload : [];
            $id = $payload['id'] ?? null;

            return match ($payload['method'] ?? null) {
                'initialize' => $this->result($id, [
                    'protocolVersion' => '2025-11-25',
                    'capabilities' => (object) [],
                    'serverInfo' => ['name' => 'Fake MCP Server', 'version' => '1.0.0'],
                ]),
                'tools/list' => $this->result($id, ['tools' => $this->tools]),
                'tools/call' => $this->callResult($id, $payload),
                default => Http::response('', 202),
            };
        };
    }

    /**
     * Build the tools/call response from the scripted results.
     *
     * @param  array<string, mixed>  $payload
     */
    private function callResult(mixed $id, array $payload): Response|PromiseInterface
    {
        $name = $payload['params']['name'] ?? null;

        if (! is_string($name) || ! array_key_exists($name, $this->results)) {
            return Http::response([
                'jsonrpc' => '2.0',
                'id' => $id,
                'error' => ['code' => -32601, 'message' => "Unknown tool [{$name}]."],
            ]);
        }

        return $this->result($id, $this->results[$name]);
    }

    /**
     * Build a successful JSON-RPC result envelope.
     *
     * @param  array<string, mixed>  $result
     */
    private function result(mixed $id, array $result): Response|PromiseInterface
    {
        return Http::response([
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ]);
    }
}
