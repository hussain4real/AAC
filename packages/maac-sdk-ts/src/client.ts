import { MaacApiError, MissingToolHandlerError, RunNotResolvedError, TransportError } from './errors.ts';
import { ToolHandlerRegistry } from './registry.ts';
import { fetchTransport } from './transport.ts';
import type { HttpRequest, HttpResponse, Transport } from './transport.ts';
import { findTool, isSettled, isWaiting } from './types.ts';
import type {
  ImplementationReport,
  ImplementationResult,
  Manifest,
  ManifestAgent,
  ManifestTool,
  Run,
  RunEvent,
  RunMode,
  SdkCompatibility,
  ToolCall,
  WebhookEndpoint,
} from './types.ts';
import { SDK_LANGUAGE, SDK_VERSION } from './version.ts';

/** Options for driving an asynchronous run via polling. */
export interface AsyncRunOptions {
  maxIterations?: number;
  maxAttempts?: number;
  intervalMs?: number;
}

const sleep = (ms: number): Promise<void> =>
  ms > 0 ? new Promise((resolve) => setTimeout(resolve, ms)) : Promise.resolve();

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

  /**
   * Start a run for a published agent. Pass `'async'` to queue a long-running
   * run for a worker (driven via polling, streaming, or webhooks) instead of
   * blocking the request.
   */
  async startRun(agentSlug: string, input: string, caller?: string, mode: RunMode = 'sync'): Promise<Run> {
    const payload: Record<string, unknown> = { input, mode };

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

  /**
   * Poll a run's status until it reaches a decision point — terminal, or paused
   * for a client-side tool — backing off between reads. This is the polling
   * integration mode for applications that cannot hold a request open.
   */
  async pollRun(runId: string, maxAttempts = 60, intervalMs = 1000): Promise<Run> {
    let run = await this.getRun(runId);

    for (let attempt = 0; !isSettled(run); attempt++) {
      if (attempt >= maxAttempts) {
        throw new RunNotResolvedError(run, `The run [${run.runId}] did not settle within ${maxAttempts} polls.`);
      }

      await sleep(intervalMs);
      run = await this.getRun(runId);
    }

    return run;
  }

  /**
   * Start an asynchronous run and drive it to completion by polling, servicing
   * each client-side tool pause from the registry. Unlike `run()`, the request
   * never blocks while the model works.
   */
  async runAsync(
    agentSlug: string,
    input: string,
    registry: ToolHandlerRegistry,
    caller?: string,
    options: AsyncRunOptions = {},
  ): Promise<Run> {
    const maxIterations = options.maxIterations ?? 16;
    const maxAttempts = options.maxAttempts ?? 60;
    const intervalMs = options.intervalMs ?? 1000;

    const started = await this.startRun(agentSlug, input, caller, 'async');
    let run = await this.pollRun(started.runId, maxAttempts, intervalMs);

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
      await this.submitToolResult(run.runId, toolCall.id, result);
      run = await this.pollRun(run.runId, maxAttempts, intervalMs);
    }

    return run;
  }

  /**
   * Register a webhook endpoint MAAC will post run lifecycle events to. The
   * returned endpoint carries its one-time signing secret — store it now to
   * verify deliveries with `verifyWebhook`.
   */
  async registerWebhook(url: string, events: string[] = ['*'], description?: string): Promise<WebhookEndpoint> {
    const payload: Record<string, unknown> = { url, events };

    if (description !== undefined) {
      payload.description = description;
    }

    const response = await this.request({
      method: 'POST',
      url: this.url('/api/v1/webhook-endpoints'),
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(payload),
    });

    return parseWebhookEndpoint(this.decode(response));
  }

  /** List the application's registered webhook endpoints for its environment. */
  async listWebhooks(): Promise<WebhookEndpoint[]> {
    const response = await this.request({
      method: 'GET',
      url: this.url('/api/v1/webhook-endpoints'),
      headers: { Accept: 'application/json' },
    });

    const rows = this.decode(response).data;

    return Array.isArray(rows)
      ? rows
          .filter((row): row is Record<string, unknown> => row !== null && typeof row === 'object')
          .map(parseWebhookEndpoint)
      : [];
  }

  /** Delete a registered webhook endpoint. */
  async deleteWebhook(id: string): Promise<void> {
    const response = await this.request({
      method: 'DELETE',
      url: this.url(`/api/v1/webhook-endpoints/${encodeURIComponent(id)}`),
      headers: { Accept: 'application/json' },
    });

    if (response.status < 200 || response.status >= 300) {
      throw MaacApiError.fromResponse(response);
    }
  }

  /**
   * Stream a run's lifecycle as Server-Sent Events. The optional callback is
   * invoked for each event; all events are also returned. The stream closes when
   * the run reaches a boundary, so the final `run.state` event carries the same
   * shape `getRun()` does.
   */
  async streamRun(runId: string, onEvent?: (event: RunEvent) => void): Promise<RunEvent[]> {
    const response = await this.request({
      method: 'GET',
      url: this.url(`/api/v1/runs/${encodeURIComponent(runId)}/stream`),
      headers: { Accept: 'text/event-stream' },
    });

    if (response.status < 200 || response.status >= 300) {
      throw MaacApiError.fromResponse(response);
    }

    const events = parseEvents(response.body);

    if (onEvent !== undefined) {
      for (const event of events) {
        onEvent(event);
      }
    }

    return events;
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
  const serverTools = Array.isArray(data.server_tools) ? data.server_tools : [];

  return {
    slug: asString(data.slug),
    name: asString(data.name),
    version: asString(data.version),
    status: asString(data.status),
    tools: tools.map((tool) => String(tool)),
    serverTools: serverTools
      .filter((tool): tool is Record<string, unknown> => tool !== null && typeof tool === 'object')
      .map((tool) => ({
        name: asString(tool.name),
        executionMode: asString(tool.execution_mode),
        description: typeof tool.description === 'string' ? tool.description : null,
      })),
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

function parseWebhookEndpoint(data: Record<string, unknown>): WebhookEndpoint {
  const events = Array.isArray(data.events) ? data.events.map((event) => String(event)) : [];

  return {
    id: asString(data.id),
    url: asString(data.url),
    events,
    environment: asString(data.environment),
    status: asString(data.status),
    secret: typeof data.secret === 'string' ? data.secret : null,
  };
}

function parseEvents(body: string): RunEvent[] {
  const events: RunEvent[] = [];

  for (const block of body.trim().split(/\r?\n\r?\n/)) {
    let name = 'message';
    const dataLines: string[] = [];

    for (const line of block.split(/\r?\n/)) {
      if (line.startsWith('event:')) {
        name = line.slice(6).trim();
      } else if (line.startsWith('data:')) {
        dataLines.push(line.slice(5).replace(/^ /, ''));
      }
    }

    const data = dataLines.join('\n');

    if (data === '' || data === '</stream>') {
      continue;
    }

    let parsed: unknown;

    try {
      parsed = JSON.parse(data);
    } catch {
      parsed = { raw: data };
    }

    events.push({
      event: name,
      data: parsed !== null && typeof parsed === 'object' && !Array.isArray(parsed) ? (parsed as Record<string, unknown>) : { raw: data },
    });
  }

  return events;
}
