import type { HttpResponse } from './transport.ts';
import type { Run } from './types.ts';

/** Base class for every error thrown by the MAAC SDK. */
export class MaacError extends Error {}

/**
 * An HTTP round-trip to MAAC could not complete, or returned an undecodable
 * body — distinct from a controlled error MAAC deliberately returned.
 */
export class TransportError extends MaacError {}

/**
 * A controlled error MAAC returned (authentication, unknown agent, oversized
 * payload, quota, ...). The MAAC error code and HTTP status are preserved.
 */
export class MaacApiError extends MaacError {
  readonly errorCode: string;
  readonly status: number;
  readonly payload: Record<string, unknown>;

  constructor(errorCode: string, message: string, status: number, payload: Record<string, unknown> = {}) {
    super(message);
    this.name = 'MaacApiError';
    this.errorCode = errorCode;
    this.status = status;
    this.payload = payload;
  }

  /** Schema-validation messages attached to an invalid_tool_result. */
  validationErrors(): string[] {
    const errors = this.payload.errors;

    return Array.isArray(errors) ? errors.filter((item): item is string => typeof item === 'string') : [];
  }

  /** Build the error from a non-2xx response, tolerating non-envelope bodies. */
  static fromResponse(response: HttpResponse): MaacApiError {
    let payload: Record<string, unknown> = {};

    try {
      const decoded: unknown = JSON.parse(response.body || '{}');

      if (decoded !== null && typeof decoded === 'object') {
        payload = decoded as Record<string, unknown>;
      }
    } catch {
      payload = {};
    }

    const code = typeof payload.error === 'string' ? payload.error : 'http_error';
    const message = typeof payload.message === 'string' ? payload.message : `MAAC returned HTTP ${response.status}.`;

    return new MaacApiError(code, message, response.status, payload);
  }
}

/**
 * The auto-resume loop paused for a client-side tool with no registered handler.
 */
export class MissingToolHandlerError extends MaacError {
  readonly tool: string;

  constructor(tool: string) {
    super(`No local handler is registered for the client-side tool [${tool}].`);
    this.name = 'MissingToolHandlerError';
    this.tool = tool;
  }
}

/** A run could not be driven to a terminal state by the auto-resume loop. */
export class RunNotResolvedError extends MaacError {
  readonly run: Run;

  constructor(run: Run, message: string) {
    super(message);
    this.name = 'RunNotResolvedError';
    this.run = run;
  }
}
