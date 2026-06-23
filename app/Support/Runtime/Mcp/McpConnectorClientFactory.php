<?php

namespace App\Support\Runtime\Mcp;

use App\Enums\RemoteAuthType;
use App\Models\McpConnector;
use Laravel\Mcp\Client;
use Laravel\Mcp\WebClient;

/**
 * Builds a configured Laravel MCP client for a registered connector: the
 * Streamable-HTTP web transport pointed at the connector's server URL, with the
 * connector's timeout and authentication applied. Resolved from the container so
 * tests can substitute a fake client while production drives the real protocol.
 */
class McpConnectorClientFactory
{
    /**
     * Build an MCP client for the connector.
     */
    public function make(McpConnector $connector): Client
    {
        $client = Client::web($connector->server_url)
            ->withTimeout((float) max(1, $connector->timeout_seconds));

        return $this->authenticate($client, $connector);
    }

    /**
     * Apply the connector's authentication scheme to the web client.
     */
    private function authenticate(WebClient $client, McpConnector $connector): WebClient
    {
        return match ($connector->auth_type) {
            RemoteAuthType::Bearer => $client->withToken((string) $connector->auth_credential),
            RemoteAuthType::Header => $client->withHeaders([
                $this->headerName($connector) => (string) $connector->auth_credential,
            ]),
            default => $client,
        };
    }

    /**
     * Resolve the custom auth header name (defaulting to Authorization).
     */
    private function headerName(McpConnector $connector): string
    {
        return $connector->auth_header !== null && $connector->auth_header !== ''
            ? $connector->auth_header
            : 'Authorization';
    }
}
