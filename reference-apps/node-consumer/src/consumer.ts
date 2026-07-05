import { MaaccClient, MaaccError, ToolHandlerRegistry } from '../../../packages/maacc-sdk-ts/src/index.ts';
import type { AsyncRunOptions, ImplementationResult, MaaccConfig, Run, Transport } from '../../../packages/maacc-sdk-ts/src/index.ts';
import { fetchRecordsHandler } from './fetchRecordsHandler.ts';

export interface NodeConsumerOptions {
  config: MaaccConfig;
  agentSlug: string;
  toolSlug: string;
  transport?: Transport;
}

/**
 * Assembles the Node/TypeScript MAACC integration: a configured SDK client plus
 * the local tool handler registry, exposing the two operations a consuming app
 * performs — sync its implementations, and run the agent.
 */
export class NodeConsumer {
  private readonly client: MaaccClient;
  private readonly registry: ToolHandlerRegistry;
  private readonly agentSlug: string;

  constructor(options: NodeConsumerOptions) {
    this.client = new MaaccClient(options.config, options.transport);
    this.registry = new ToolHandlerRegistry().register(options.toolSlug, fetchRecordsHandler, 'fetchRecordsHandler');
    this.agentSlug = options.agentSlug;
  }

  /** The underlying SDK client. */
  maacc(): MaaccClient {
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

  /**
   * Invoke the agent as a long-running asynchronous run and drive it to
   * completion by polling — the integration mode for a process that cannot hold
   * an HTTP request open while the model works.
   */
  async runAsync(prompt: string, caller = 'node-reference', options: AsyncRunOptions = {}): Promise<Run> {
    return this.client.runAsync(this.agentSlug, prompt, this.registry, caller, options);
  }

  /** Build the consumer from the documented MAACC_* environment variables. */
  static fromEnvironment(transport?: Transport): NodeConsumer {
    return new NodeConsumer({
      config: {
        baseUrl: requireEnv('MAACC_BASE_URL'),
        clientId: requireEnv('MAACC_CLIENT_ID'),
        clientSecret: requireEnv('MAACC_CLIENT_SECRET'),
      },
      agentSlug: process.env.MAACC_AGENT_SLUG ?? 'e2e-ops-agent',
      toolSlug: process.env.MAACC_TOOL_FETCH_RECORDS ?? 'e2e-fetch-records',
      transport,
    });
  }
}

function requireEnv(key: string): string {
  const value = process.env[key];

  if (value === undefined || value === '') {
    throw new MaaccError(`Missing required environment variable: ${key}.`);
  }

  return value;
}
