import type { Run, ToolCall } from './types.ts';

/** The context handed to a tool handler when MAAC pauses a run for it. */
export interface ToolContext {
  run: Run;
  toolCall: ToolCall;
}

/**
 * A local implementation of a client-side tool. This is where the application's
 * own business logic and data access live; MAAC sees only the returned result,
 * which must satisfy the tool contract's output schema.
 */
export type ToolHandler = (
  args: Record<string, unknown>,
  context: ToolContext,
) => Record<string, unknown> | Promise<Record<string, unknown>>;

interface RegisteredHandler {
  name: string;
  handle: ToolHandler;
}

/** The application's registry of client-side tool handlers, keyed by tool slug. */
export class ToolHandlerRegistry {
  private readonly handlers = new Map<string, RegisteredHandler>();

  /**
   * Register a handler for a tool slug. The optional display name is reported to
   * MAAC's SDK Implementation Center (defaults to the function name).
   */
  register(tool: string, handler: ToolHandler, name?: string): this {
    this.handlers.set(tool, { name: name ?? (handler.name || 'Handler'), handle: handler });

    return this;
  }

  has(tool: string): boolean {
    return this.handlers.has(tool);
  }

  resolve(tool: string): ToolHandler | null {
    return this.handlers.get(tool)?.handle ?? null;
  }

  nameFor(tool: string): string {
    return this.handlers.get(tool)?.name ?? 'UnknownHandler';
  }

  registered(): string[] {
    return [...this.handlers.keys()];
  }
}
