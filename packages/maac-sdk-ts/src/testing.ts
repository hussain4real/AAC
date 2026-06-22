/**
 * Pre-flight test helpers for application teams: validate a local client-side
 * tool handler against its MAAC contract — both the arguments it will receive
 * and the result it returns — *before* reporting it as implemented. Mirrors
 * MAAC's server-side `ToolSchema` exactly (same rules, same messages); the
 * shared contract fixture suite (packages/sdk-fixtures) keeps them in lock-step.
 */
import type { ToolContext, ToolHandler } from './registry.ts';
import type { ManifestTool, Run, ToolCall } from './types.ts';

/**
 * The outcome of validating a payload (or a handler's input/output) against a
 * MAAC tool contract schema.
 */
export interface ValidationResult {
  valid: boolean;
  errors: string[];
}

/** Extract the base type from a definition (strip the optional marker + format hint). */
export function baseType(definition: string): string {
  const head = definition.split('·')[0] ?? '';

  return head
    .trim()
    .replace(/\?+$/, '')
    .trim();
}

/** Whether a field definition marks the field optional. */
export function isOptional(definition: string): boolean {
  return (definition.split('·')[0] ?? '').includes('?');
}

/**
 * Validate a payload against a MAAC tool contract schema map. Unknown extra
 * fields are tolerated; missing required fields and type mismatches are not.
 */
export function validateSchema(
  schema: Record<string, unknown>,
  payload: Record<string, unknown>,
): ValidationResult {
  const errors: string[] = [];

  for (const [field, definition] of Object.entries(schema)) {
    if (typeof definition !== 'string') {
      continue;
    }

    if (!Object.prototype.hasOwnProperty.call(payload, field)) {
      if (!isOptional(definition)) {
        errors.push(`Missing required field "${field}".`);
      }

      continue;
    }

    const base = baseType(definition);

    if (!valueMatchesType(payload[field], base)) {
      errors.push(`Field "${field}" must be of type ${base}.`);
    }
  }

  return { valid: errors.length === 0, errors };
}

/**
 * Compare two `x.y.z` semantic versions, returning -1, 0, or 1. Matches PHP's
 * `version_compare` for the clean numeric versions MAAC contracts use.
 */
export function compareVersions(a: string, b: string): number {
  const pa = a.split('.').map((part) => Number.parseInt(part, 10) || 0);
  const pb = b.split('.').map((part) => Number.parseInt(part, 10) || 0);
  const length = Math.max(pa.length, pb.length);

  for (let i = 0; i < length; i++) {
    const da = pa[i] ?? 0;
    const db = pb[i] ?? 0;

    if (da !== db) {
      return da < db ? -1 : 1;
    }
  }

  return 0;
}

/**
 * The client-side mirror of MAAC's tool-implementation compatibility rule: an
 * incompatible fingerprint wins, then an older version is outdated, otherwise it
 * is implemented. Lets an application predict the status MAAC will assign before
 * it reports. Kept in lock-step with the server by the shared fixture suite.
 */
export function evaluateCompatibility(
  reportedVersion: string,
  currentVersion: string,
  reportedFingerprint?: string | null,
  currentFingerprint?: string | null,
): string {
  if (reportedFingerprint != null && reportedFingerprint !== currentFingerprint) {
    return 'incompatible';
  }

  if (compareVersions(reportedVersion, currentVersion) < 0) {
    return 'outdated';
  }

  return 'implemented';
}

function valueMatchesType(value: unknown, base: string): boolean {
  switch (base) {
    case 'string':
      return typeof value === 'string';
    case 'number':
      return typeof value === 'number';
    case 'integer':
      return typeof value === 'number' && Number.isInteger(value);
    case 'boolean':
      return typeof value === 'boolean';
    case 'object':
      return typeof value === 'object' && value !== null && !Array.isArray(value);
    case 'array':
      return Array.isArray(value);
    default:
      return false;
  }
}

/**
 * A harness that validates a local tool handler against its MAAC contract before
 * it is reported as implemented.
 */
export class ToolTester {
  /** Validate sample arguments against the tool's input schema. */
  validateInput(tool: ManifestTool, args: Record<string, unknown>): ValidationResult {
    return validateSchema(tool.inputSchema, args);
  }

  /** Validate a result against the tool's output schema. */
  validateOutput(tool: ManifestTool, result: Record<string, unknown>): ValidationResult {
    return validateSchema(tool.outputSchema, result);
  }

  /**
   * Run a handler against sample arguments and validate both the arguments and
   * the returned result. Errors are prefixed `input:`/`output:` so it is obvious
   * which side of the contract failed.
   */
  async test(
    tool: ManifestTool,
    handler: ToolHandler,
    args: Record<string, unknown>,
  ): Promise<ValidationResult> {
    const errors = this.validateInput(tool, args).errors.map((error) => `input: ${error}`);
    const result = await handler(args, syntheticContext(tool, args));

    for (const error of this.validateOutput(tool, result).errors) {
      errors.push(`output: ${error}`);
    }

    return { valid: errors.length === 0, errors };
  }
}

function syntheticContext(tool: ManifestTool, args: Record<string, unknown>): ToolContext {
  const toolCall: ToolCall = {
    id: 'test-tool-call',
    tool: tool.name,
    arguments: args,
    outputSchema: tool.outputSchema,
  };

  const run: Run = {
    runId: 'test-run',
    agentSlug: '',
    status: 'waiting_for_client',
    tokensIn: 0,
    tokensOut: 0,
    cost: 0,
    response: null,
    toolCall,
    error: null,
  };

  return { run, toolCall };
}
