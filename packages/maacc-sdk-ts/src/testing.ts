/**
 * Pre-flight test helpers for application teams: validate a local client-side
 * tool handler against its MAACC contract — both the arguments it will receive
 * and the result it returns — *before* reporting it as implemented. Mirrors
 * MAACC's server-side `ToolSchema` exactly (same rules, same messages); the
 * shared contract fixture suite (packages/sdk-fixtures) keeps them in lock-step.
 */
import type { ToolContext, ToolHandler } from './registry.ts';
import type { ManifestTool, Run, ToolCall } from './types.ts';

/**
 * The outcome of validating a payload (or a handler's input/output) against a
 * MAACC tool contract schema.
 */
export interface ValidationResult {
  valid: boolean;
  errors: string[];
}

/** The versioned compact-schema dialect implemented by server and SDKs. */
export const SCHEMA_DIALECT = 'https://maacc.dev/schema/compact/1.0';

/** Extract the base type from a legacy string or rich definition object. */
export function baseType(definition: unknown): string {
  if (isRecord(definition)) {
    return typeof definition.type === 'string' ? definition.type.trim() : '';
  }

  if (typeof definition !== 'string') {
    return '';
  }

  const head = definition.split('·')[0] ?? '';

  return head
    .trim()
    .replace(/\?+$/, '')
    .trim();
}

/** Whether a field definition marks the field optional. */
export function isOptional(definition: unknown): boolean {
  if (isRecord(definition)) {
    return definition.required === false;
  }

  return typeof definition === 'string' && (definition.split('·')[0] ?? '').includes('?');
}

/**
 * Validate a payload against the closed top-level MAACC compact schema.
 */
export function validateSchema(
  schema: Record<string, unknown>,
  payload: Record<string, unknown>,
): ValidationResult {
  const errors = validateObject(schema, payload, '', false);

  return { valid: errors.length === 0, errors };
}

function validateObject(
  schema: Record<string, unknown>,
  payload: Record<string, unknown>,
  prefix: string,
  additionalProperties: boolean,
): string[] {
  const errors: string[] = [];

  for (const [field, definition] of Object.entries(schema)) {
    const path = prefix === '' ? field : `${prefix}.${field}`;

    if (!Object.prototype.hasOwnProperty.call(payload, field)) {
      if (!isOptional(definition)) {
        errors.push(`Missing required field "${path}".`);
      }

      continue;
    }

    for (const error of validateValue(definition, payload[field], path)) {
      errors.push(error);
    }
  }

  if (!additionalProperties) {
    for (const field of Object.keys(payload)) {
      if (!Object.prototype.hasOwnProperty.call(schema, field)) {
        const path = prefix === '' ? field : `${prefix}.${field}`;
        errors.push(`Field "${path}" is not declared by the schema.`);
      }
    }
  }

  return errors;
}

function validateValue(definition: unknown, value: unknown, path: string): string[] {
  const base = baseType(definition);

  if (!valueMatchesType(value, base)) {
    return [`Field "${path}" must be of type ${base}.`];
  }

  if (!isRecord(definition)) {
    const format = typeof definition === 'string' ? definition.split('·')[1] : undefined;

    return format !== undefined && !matchesFormat(value, format)
      ? [`Field "${path}" must match format ${format}.`]
      : [];
  }

  const errors: string[] = [];

  if (Array.isArray(definition.enum) && !definition.enum.some((candidate) => Object.is(candidate, value))) {
    errors.push(`Field "${path}" must be one of the declared enum values.`);
  }

  if (typeof value === 'string') {
    if (typeof definition.minLength === 'number' && value.length < definition.minLength) {
      errors.push(`Field "${path}" is shorter than minLength.`);
    }

    if (typeof definition.maxLength === 'number' && value.length > definition.maxLength) {
      errors.push(`Field "${path}" exceeds maxLength.`);
    }

    if (typeof definition.format === 'string' && !matchesFormat(value, definition.format)) {
      errors.push(`Field "${path}" must match format ${definition.format}.`);
    }
  }

  if (typeof value === 'number') {
    if (typeof definition.minimum === 'number' && value < definition.minimum) {
      errors.push(`Field "${path}" is below minimum.`);
    }

    if (typeof definition.maximum === 'number' && value > definition.maximum) {
      errors.push(`Field "${path}" exceeds maximum.`);
    }
  }

  if (base === 'object' && isRecord(value) && isRecord(definition.properties)) {
    errors.push(...validateObject(definition.properties, value, path, definition.additionalProperties === true));
  }

  if (base === 'array' && Array.isArray(value)) {
    if (typeof definition.minItems === 'number' && value.length < definition.minItems) {
      errors.push(`Field "${path}" has fewer than minItems entries.`);
    }

    if (typeof definition.maxItems === 'number' && value.length > definition.maxItems) {
      errors.push(`Field "${path}" exceeds maxItems.`);
    }

    if (Object.prototype.hasOwnProperty.call(definition, 'items')) {
      value.forEach((item, index) => errors.push(...validateValue(definition.items, item, `${path}[${index}]`)));
    }
  }

  return errors;
}

/**
 * Compare two `x.y.z` semantic versions, returning -1, 0, or 1. Matches PHP's
 * `version_compare` for the clean numeric versions MAACC contracts use.
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
 * The client-side mirror of MAACC's tool-implementation compatibility rule: an
 * incompatible fingerprint wins, then an older version is outdated, otherwise it
 * is implemented. Lets an application predict the status MAACC will assign before
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

function isRecord(value: unknown): value is Record<string, unknown> {
  return value !== null && typeof value === 'object' && !Array.isArray(value);
}

function matchesFormat(value: unknown, format: string): boolean {
  if (typeof value !== 'string') {
    return false;
  }

  switch (format) {
    case 'date': {
      if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return false;
      }

      const date = new Date(`${value}T00:00:00Z`);

      return !Number.isNaN(date.valueOf()) && date.toISOString().slice(0, 10) === value;
    }
    case 'date-time': {
      const match = /^(\d{4}-\d{2}-\d{2})T(\d{2}):(\d{2}):(\d{2})(?:Z|[+-](\d{2}):(\d{2}))$/.exec(value);

      if (match === null) {
        return false;
      }

      const [, date, hour, minute, second, offsetHour, offsetMinute] = match;

      return (
        matchesFormat(date, 'date') &&
        Number(hour) <= 23 &&
        Number(minute) <= 59 &&
        Number(second) <= 59 &&
        (offsetHour === undefined || Number(offsetHour) <= 23) &&
        (offsetMinute === undefined || Number(offsetMinute) <= 59)
      );
    }
    case 'email':
      return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
    case 'uuid':
      return /^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(value);
    case 'uri':
      try {
        new URL(value);

        return true;
      } catch {
        return false;
      }
    default:
      return true;
  }
}

/**
 * A harness that validates a local tool handler against its MAACC contract before
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
    callerContext: {},
  };

  return { run, toolCall, callerContext: run.callerContext };
}
