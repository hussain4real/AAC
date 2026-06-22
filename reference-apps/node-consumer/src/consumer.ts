import { MaacClient, MaacError, ToolHandlerRegistry } from '../../../packages/maac-sdk-ts/src/index.ts';
import type { ImplementationResult, MaacConfig, Run, Transport } from '../../../packages/maac-sdk-ts/src/index.ts';
import { fetchRecordsHandler } from './fetchRecordsHandler.ts';

export interface NodeConsumerOptions {
  config: MaacConfig;
  agentSlug: string;
  toolSlug: string;
  transport?: Transport;
}

/**
 * Assembles the Node/TypeScript MAAC integration: a configured SDK client plus
 * the local tool handler registry, exposing the two operations a consuming app
 * performs — sync its implementations, and run the agent.
 */
export class NodeConsumer {
  private readonly client: MaacClient;
  private readonly registry: ToolHandlerRegistry;
  private readonly agentSlug: string;

  constructor(options: NodeConsumerOptions) {
    this.client = new MaacClient(options.config, options.transport);
    this.registry = new ToolHandlerRegistry().register(options.toolSlug, fetchRecordsHandler, 'fetchRecordsHandler');
    this.agentSlug = options.agentSlug;
  }

  /** The underlying SDK client. */
  maac(): MaacClient {
    return this.client;
  }

  /** Report every registered handler against the current manifest. */
  async syncImplementations(): Promise<ImplementationResult[]> {
    return this.client.reportHandlers(await this.client.manifest(), this.registry, 'typescript');
  }

  /** Invoke the agent and drive it to a terminal state via local handlers. */
  async run(prompt: string, caller = 'node-reference'): Promise<Run> {
    return this.client.run(this.agentSlug, prompt, this.registry, caller);
  }

  /** Build the consumer from the documented MAAC_* environment variables. */
  static fromEnvironment(transport?: Transport): NodeConsumer {
    return new NodeConsumer({
      config: {
        baseUrl: requireEnv('MAAC_BASE_URL'),
        clientId: requireEnv('MAAC_CLIENT_ID'),
        clientSecret: requireEnv('MAAC_CLIENT_SECRET'),
      },
      agentSlug: process.env.MAAC_AGENT_SLUG ?? 'e2e-ops-agent',
      toolSlug: process.env.MAAC_TOOL_FETCH_RECORDS ?? 'e2e-fetch-records',
      transport,
    });
  }
}

function requireEnv(key: string): string {
  const value = process.env[key];

  if (value === undefined || value === '') {
    throw new MaacError(`Missing required environment variable: ${key}.`);
  }

  return value;
}
