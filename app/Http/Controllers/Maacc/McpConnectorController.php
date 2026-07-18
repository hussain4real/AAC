<?php

namespace App\Http\Controllers\Maacc;

use App\Enums\McpConnectorStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maacc\StoreMcpConnectorRequest;
use App\Http\Requests\Maacc\UpdateMcpConnectorRequest;
use App\Models\McpConnector;
use App\Support\Runtime\Mcp\McpCapabilityDiscoverer;
use App\Support\Runtime\ToolExecutionException;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Console management of MCP connectors: register an external MCP server,
 * discover its capabilities, update its config/status, and remove it. Auth
 * credential material is stored encrypted and never returned to the console.
 */
class McpConnectorController extends Controller
{
    /**
     * Register a new MCP connector.
     */
    public function store(StoreMcpConnectorRequest $request): RedirectResponse
    {
        Gate::authorize('create', McpConnector::class);

        $team = $request->user()->currentTeam()->firstOrFail();

        $connector = new McpConnector([
            ...$request->validated(),
            'team_id' => $team->id,
            'slug' => Slug::unique('mcp_connectors', $request->validated('name')),
            'transport' => 'http',
            'status' => McpConnectorStatus::PendingVerification,
            'created_by' => $request->user()?->getAuthIdentifier(),
        ]);
        $connector->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Connector registered.']);

        return back();
    }

    /**
     * Update the given connector, preserving the credential when not re-entered.
     */
    public function update(UpdateMcpConnectorRequest $request, string $currentTeam, McpConnector $mcpConnector): RedirectResponse
    {
        Gate::authorize('update', $mcpConnector);

        $data = $request->validated();

        if (! Arr::hasAny($data, ['auth_credential']) || blank($data['auth_credential'] ?? null)) {
            unset($data['auth_credential']);
        }

        if (Arr::hasAny($data, ['server_url', 'auth_type', 'auth_credential', 'auth_header'])) {
            $data['status'] = McpConnectorStatus::PendingVerification;
            $data['capabilities'] = null;
            $data['last_discovered_at'] = null;
        }

        $mcpConnector->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Connector updated.']);

        return back();
    }

    /**
     * Discover and persist the connector's remote capabilities.
     */
    public function discover(string $currentTeam, McpConnector $mcpConnector, McpCapabilityDiscoverer $discoverer): RedirectResponse
    {
        Gate::authorize('update', $mcpConnector);

        try {
            $capabilities = $discoverer->discover($mcpConnector);
        } catch (ToolExecutionException $exception) {
            Inertia::flash('toast', ['type' => 'error', 'message' => "Discovery failed: {$exception->getMessage()}"]);

            return back();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => count($capabilities).' remote tool(s) discovered.']);

        return back();
    }

    /**
     * Delete the given connector.
     */
    public function destroy(Request $request, string $currentTeam, McpConnector $mcpConnector): RedirectResponse
    {
        Gate::authorize('delete', $mcpConnector);

        $mcpConnector->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Connector removed.']);

        return back();
    }
}
