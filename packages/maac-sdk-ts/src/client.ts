import { MaacApiError, MissingToolHandlerError, RunNotResolvedError, TransportError } from './errors.ts';
import { ToolHandlerRegistry } from './registry.ts';
import { fetchTransport } from './transport.ts';
import type { HttpRequest, HttpResponse, Transport } from './transport.ts';
import { findTool, isWaiting } from './types.ts';
import type {
  ImplementationReport,
  ImplementationResult,
  Manifest,
  ManifestAgent,
  ManifestTool,
  Run,
  SdkCompatibility,
  ToolCall,
} from './types.ts';
import { SDK_LANGUAGE, SDK_VERSION } from './version.ts';

/** Connection configuration for a MAAC application credential. */
export interface MaacConfig {
  baseUrl: string;
  clientId: string;
  clientSecret: string;
}

interface CachedToken {
  token: string;
  expiresAt: number;
}

/**
 * The public entry point for integrating a TypeScript/Node application with
 * MAAC. It speaks only the documented SDK and runtime contracts — token
 * exchange, manifest sync, implementation reporting, agent invocation, and
 * client-side tool pause/resume — proving the integration is not Laravel- or
 * PHP-specific.
 */
export class MaacClient {
  private readonly config: MaacConfig;
  private readonly transport: Transport;
  private token: CachedToken | null = null;

  constructor(config: MaacConfig, transport: Transport = fetchTransport) {
    this.config = config;
    this.transport = transport;
  }

  /** Eagerly exchange the credential for an access token and return it. */
  async authenticate(): Promise<string> {
    return this.accessToken();
  }

  /**
   * Ask MAAC whether this installed SDK client is compatible with the server's
   * current API contract. Reports `SDK_VERSION` by default; pass an explicit
   * version to probe a different one.
   */
  async compatibility(clientVersion?: string): Promise<SdkCompatibility> {
    const response = await this.request({
      method: 'GET',
      url: this.url('/api/v1/sdk'),
      headers: {
        Accept: 'application/json',
        'X-Maac-Sdk-Version': clientVersion ?? SDK_VERSION,
        'X-Maac-Sdk-Language': SDK_LANGUAGE,
      },
    });

    return parseCompatibility(this.decode(response));
  }

  /** Fetch the SDK manifest for the credential's environment. */
  async manifest(): Promise<Manifest> {
    const response = await this.request({
      method: 'GET',
      url: this.url('/api/v1/manifest'),
      headers: { Accept: 'application/json' },
    });

    return parseManifest(this.decode(response));
  }

  /**
   * Report a batch of local tool-handler implementations.
   */
  async reportImplementations(reports: ImplementationReport[]): Promise<ImplementationResult[]> {
    const response = await this.request({
      method: 'POST',
      url: this.url('/api/v1/tool-implementations'),
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ implementations: reports.map(toWireReport) }),
    });

    const results = this.decode(response).results;

    return Array.isArray(results) ? (results as ImplementationResult[]) : [];
  }

  /**
   * Report every handler in the registry that the manifest still expects,
   * reusing each contract's current version and fingerprint.
   */
  async reportHandlers(
    manifest: Manifest,
    registry: ToolHandlerRegistry,
    language = 'typescript',
  ): Promise<ImplementationResult[]> {
    const reports: ImplementationReport[] = [];

    for (const tool of registry.registered()) {
      const contract = findTool(manifest, tool);

      if (contract === null) {
        continue;
      }

      reports.push({
        tool: contract.name,
        handlerName: registry.nameFor(tool),
        version: contract.version,
        schemaFingerprint: contract.schemaFingerprint,
        language,
      });
    }

    return reports.length === 0 ? [] : this.reportImplementations(reports);
  }

  /** Start a run for a published agent. */
  async startRun(agentSlug: string, input: string, caller?: string): Promise<Run> {
    const payload: Record<string, unknown> = { input };

    if (caller !== undefined) {
      payload.caller = caller;
    }

    const response = await this.request({
      method: 'POST',
      url: this.url(`/api/v1/agents/${encodeURIComponent(agentSlug)}/runs`),
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
    });

    return parseRun(this.decode(response));
  }

  /** Read the current status of a run. */
  async getRun(runId: string): Promise<Run> {
    const response = await this.request({
      method: 'GET',
      url: this.url(`/api/v1/runs/${encodeURIComponent(runId)}`),
      headers: { Accept: 'application/json' },
    });

    return parseRun(this.decode(response));
  }

  /** Submit a client-side tool result for a paused run, resuming it. */
  async submitToolResult(runId: string, toolCallId: string, result: Record<string, unknown>): Promise<Run> {
    const response = await this.request({
      method: 'POST',
      url: this.url(`/api/v1/runs/${encodeURIComponent(runId)}/tool-results`),
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ tool_call_id: toolCallId, result }),
    });

    return parseRun(this.decode(response));
  }

  /**
   * Start a run and drive it to completion, servicing each client-side tool
   * pause from the registry. Returns the terminal run.
   */
  async run(
    agentSlug: string,
    input: string,
    registry: ToolHandlerRegistry,
    caller?: string,
    maxIterations = 16,
  ): Promise<Run> {
    let run = await this.startRun(agentSlug, input, caller);

    for (let iteration = 0; isWaiting(run); iteration++) {
      if (iteration >= maxIterations) {
        throw new RunNotResolvedError(run, `The run [${run.runId}] did not finish within ${maxIterations} tool iterations.`);
      }

      const toolCall = run.toolCall;

      if (toolCall === null) {
        throw new RunNotResolvedError(run, `The run [${run.runId}] is waiting but MAAC returned no pending tool call.`);
      }

      const handler = registry.resolve(toolCall.tool);

      if (handler === null) {
        throw new MissingToolHandlerError(toolCall.tool);
      }

      const result = await handler(toolCall.arguments, { run, toolCall });
      run = await this.submitToolResult(run.runId, toolCall.id, result);
    }

    return run;
  }

  private url(path: string): string {
    return `${this.config.baseUrl.replace(/\/+$/, '')}/${path.replace(/^\/+/, '')}`;
  }

  private async accessToken(): Promise<string> {
    const now = Math.floor(Date.now() / 1000);

    if (this.token === null || now >= this.token.expiresAt) {
      this.token = await this.exchange();
    }

    return this.token.token;
  }

  private async exchange(): Promise<CachedToken> {
    const body = new URLSearchParams({
      grant_type: 'client_credentials',
      client_id: this.config.clientId,
      client_secret: this.config.clientSecret,
    }).toString();

    const response = await this.transport({
      method: 'POST',
      url: this.url('/oauth/token'),
      headers: { 'Content-Type': 'application/x-www-form-urlencoded', Accept: 'application/json' },
      body,
    });

    const json = this.decode(response);
    const expiresIn = typeof json.expires_in === 'number' ? json.expires_in : 3600;

    return {
      token: typeof json.access_token === 'string' ? json.access_token : '',
      expiresAt: Math.floor(Date.now() / 1000) + Math.max(0, expiresIn - 30),
    };
  }

  private async request(request: HttpRequest): Promise<HttpResponse> {
    const token = await this.accessToken();
    const response = await this.transport(this.authorize(request, token));

    if (response.status !== 401) {
      return response;
    }

    const fresh = await this.refresh();

    return this.transport(this.authorize(request, fresh));
  }

  /**
   * Stamp a request with the bearer token and the SDK version/language headers.
   * The request's own headers take precedence, so `compatibility()` can probe an
   * explicit version.
   */
  private authorize(request: HttpRequest, token: string): HttpRequest {
    return {
      ...request,
      headers: {
        'X-Maac-Sdk-Version': SDK_VERSION,
        'X-Maac-Sdk-Language': SDK_LANGUAGE,
        ...request.headers,
        Authorization: `Bearer ${token}`,
      },
    };
  }

  private async refresh(): Promise<string> {
    this.token = await this.exchange();

    return this.token.token;
  }

  private decode(response: HttpResponse): Record<string, unknown> {
    if (response.status < 200 || response.status >= 300) {
      throw MaacApiError.fromResponse(response);
    }

    let parsed: unknown;

    try {
      parsed = JSON.parse(response.body === '' ? '{}' : response.body);
    } catch {
      throw new TransportError(`MAAC returned a non-JSON response (HTTP ${response.status}).`);
    }

    if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
      throw new TransportError(`MAAC returned a non-object JSON response (HTTP ${response.status}).`);
    }

    return parsed as Record<string, unknown>;
  }
}

function toWireReport(report: ImplementationReport): Record<string, unknown> {
  return {
    tool: report.tool,
    handler_name: report.handlerName,
    version: report.version,
    schema_fingerprint: report.schemaFingerprint ?? null,
    language: report.language ?? SDK_LANGUAGE,
    sdk_version: report.sdkVersion ?? SDK_VERSION,
  };
}

function parseCompatibility(data: Record<string, unknown>): SdkCompatibility {
  const compatibility = asObject(data.compatibility);
  const deprecations = Array.isArray(data.deprecations)
    ? data.deprecations.filter((item): item is Record<string, unknown> => item !== null && typeof item === 'object')
    : [];

  return {
    compatible: compatibility.compatible === true,
    status: asString(compatibility.status) || 'unknown',
    clientVersion: typeof compatibility.client_version === 'string' ? compatibility.client_version : null,
    apiVersion: asString(compatibility.api_version) || asString(data.api_version),
    minimumClientVersion: asString(compatibility.minimum_client_version) || asString(data.minimum_client_version),
    currentClientVersion: asString(compatibility.current_client_version) || asString(data.current_client_version),
    upgradeRequired: compatibility.upgrade_required === true,
    deprecations,
  };
}

function asObject(value: unknown): Record<string, unknown> {
  return value !== null && typeof value === 'object' && !Array.isArray(value) ? (value as Record<string, unknown>) : {};
}

function asString(value: unknown): string {
  return typeof value === 'string' ? value : '';
}

function asNumber(value: unknown): number {
  return typeof value === 'number' ? value : 0;
}

function parseToolCall(data: Record<string, unknown>): ToolCall {
  return {
    id: asString(data.id),
    tool: asString(data.tool),
    arguments: asObject(data.arguments),
    outputSchema:
      data.output_schema !== null && typeof data.output_schema === 'object'
        ? (data.output_schema as Record<string, unknown>)
        : null,
  };
}

function parseRun(data: Record<string, unknown>): Run {
  const usage = asObject(data.usage);

  return {
    runId: asString(data.run_id),
    agentSlug: asString(data.agent_slug),
    status: asString(data.status),
    tokensIn: asNumber(usage.tokens_in),
    tokensOut: asNumber(usage.tokens_out),
    cost: asNumber(data.cost),
    response: typeof data.response === 'string' ? data.response : null,
    toolCall: data.tool_call !== null && typeof data.tool_call === 'object'
      ? parseToolCall(data.tool_call as Record<string, unknown>)
      : null,
    error: typeof data.error === 'string' ? data.error : null,
  };
}

function parseAgent(data: Record<string, unknown>): ManifestAgent {
  const tools = Array.isArray(data.tools) ? data.tools : [];

  return {
    slug: asString(data.slug),
    name: asString(data.name),
    version: asString(data.version),
    status: asString(data.status),
    tools: tools.map((tool) => String(tool)),
  };
}

function parseTool(data: Record<string, unknown>): ManifestTool {
  const implementation = asObject(data.implementation);

  return {
    name: asString(data.name),
    version: asString(data.version),
    schemaFingerprint: asString(data.schema_fingerprint),
    inputSchema: asObject(data.input_schema),
    outputSchema: asObject(data.output_schema),
    implementation: {
      status: typeof implementation.status === 'string' ? implementation.status : 'required',
      handlerName: typeof implementation.handler_name === 'string' ? implementation.handler_name : null,
      implementedVersion: typeof implementation.implemented_version === 'string' ? implementation.implemented_version : null,
      lastValidatedAt: typeof implementation.last_validated_at === 'string' ? implementation.last_validated_at : null,
    },
  };
}

function parseManifest(data: Record<string, unknown>): Manifest {
  const application = asObject(data.application);
  const agents = Array.isArray(data.agents) ? data.agents : [];
  const tools = Array.isArray(data.tools) ? data.tools : [];

  return {
    environment: asString(application.environment),
    agents: agents.filter((agent): agent is Record<string, unknown> => agent !== null && typeof agent === 'object').map(parseAgent),
    tools: tools.filter((tool): tool is Record<string, unknown> => tool !== null && typeof tool === 'object').map(parseTool),
  };
}
