/**
 * Typed models of MAAC's SDK/runtime response envelopes, plus the small status
 * helpers consumers branch on. Field names are normalised to camelCase from the
 * snake_case wire format by the client.
 */

export type ImplementationStatus =
  | 'required'
  | 'implemented'
  | 'outdated'
  | 'incompatible'
  | 'disabled'
  | 'not_required';

export type RunStatus =
  | 'queued'
  | 'running'
  | 'requires_tool'
  | 'waiting_for_client'
  | 'completed'
  | 'failed'
  | 'expired'
  | 'cancelled';

/** How a run is invoked: a blocking `sync` run or a worker-backed `async` run. */
export type RunMode = 'sync' | 'async';

export interface ToolCall {
  id: string;
  tool: string;
  arguments: Record<string, unknown>;
  outputSchema: Record<string, unknown> | null;
}

export interface Run {
  runId: string;
  agentSlug: string;
  status: RunStatus | string;
  tokensIn: number;
  tokensOut: number;
  cost: number;
  response: string | null;
  toolCall: ToolCall | null;
  error: string | null;
}

export interface ManifestServerTool {
  name: string;
  executionMode: string;
  description: string | null;
}

export interface ManifestAgent {
  slug: string;
  name: string;
  version: string;
  status: string;
  tools: string[];
  serverTools: ManifestServerTool[];
}

export interface ManifestToolImplementation {
  status: string;
  handlerName: string | null;
  implementedVersion: string | null;
  lastValidatedAt: string | null;
}

export interface ManifestTool {
  name: string;
  version: string;
  schemaFingerprint: string;
  inputSchema: Record<string, unknown>;
  outputSchema: Record<string, unknown>;
  implementation: ManifestToolImplementation;
}

export interface Manifest {
  environment: string;
  agents: ManifestAgent[];
  tools: ManifestTool[];
}

export interface ImplementationReport {
  tool: string;
  handlerName: string;
  version: string;
  schemaFingerprint?: string | null;
  language?: string;
  sdkVersion?: string;
}

/**
 * MAAC's verdict on whether the installed SDK client is compatible with a MAAC
 * instance, returned by `MaacClient.compatibility()`. `status` is one of
 * `compatible`, `upgrade_required`, `ahead`, or `unknown`.
 */
export interface SdkCompatibility {
  compatible: boolean;
  status: string;
  clientVersion: string | null;
  apiVersion: string;
  minimumClientVersion: string;
  currentClientVersion: string;
  upgradeRequired: boolean;
  deprecations: Array<Record<string, unknown>>;
}

export interface ImplementationResult {
  tool: string;
  accepted: boolean;
  status?: string;
  error?: string;
  [key: string]: unknown;
}

/** A webhook endpoint registered with MAAC. `secret` is present only on registration. */
export interface WebhookEndpoint {
  id: string;
  url: string;
  events: string[];
  environment: string;
  status: string;
  secret: string | null;
}

/** A single Server-Sent Event from a run stream. */
export interface RunEvent {
  event: string;
  data: Record<string, unknown>;
}

const TERMINAL_STATUSES: ReadonlyArray<string> = ['completed', 'failed', 'expired', 'cancelled'];

/** Whether the run is paused awaiting a client-side tool result. */
export function isWaiting(run: Run): boolean {
  return run.status === 'waiting_for_client';
}

/** Whether the run finished successfully. */
export function isCompleted(run: Run): boolean {
  return run.status === 'completed';
}

/** Whether the run reached a terminal status and will not change again. */
export function isTerminal(run: Run): boolean {
  return TERMINAL_STATUSES.includes(run.status);
}

/**
 * Whether the run has reached a decision point a poller should stop on: it is
 * terminal, or it is paused waiting for a client-side tool result.
 */
export function isSettled(run: Run): boolean {
  return isTerminal(run) || isWaiting(run);
}

/** Find a manifest tool by slug. */
export function findTool(manifest: Manifest, name: string): ManifestTool | null {
  return manifest.tools.find((tool) => tool.name === name) ?? null;
}

/** Find a manifest agent by slug. */
export function findAgent(manifest: Manifest, slug: string): ManifestAgent | null {
  return manifest.agents.find((agent) => agent.slug === slug) ?? null;
}

/** Whether MAAC considers the tool implemented and compatible. */
export function isImplemented(tool: ManifestTool): boolean {
  return tool.implementation.status === 'implemented';
}

/** Whether the installed SDK client can safely talk to this MAAC instance. */
export function isSdkCompatible(compatibility: SdkCompatibility): boolean {
  return compatibility.compatible;
}
