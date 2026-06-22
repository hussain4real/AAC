import type { HttpResponse } from './transport.ts';
import type { Run } from './types.ts';

/** Base class for every error thrown by the MAAC SDK. */
export class MaacError extends Error {}

/**
 * An HTTP round-trip to MAAC could not complete, or returned an undecodable
 * body — distinct from a controlled error MAAC deliberately returned.
 */
export class TransportError extends MaacError {
  static fromFetchError(url: string, error: unknown): TransportError {
    const cause = fetchCause(error);
    const detail = cause.message !== '' ? `: ${cause.message}` : '';
    const hint = fetchHint(cause);

    return new TransportError(`Could not reach MAAC at ${url}${detail}${hint}`);
  }
}

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

interface FetchCause {
  code?: string;
  message: string;
}

function fetchCause(error: unknown): FetchCause {
  if (error instanceof Error) {
    const cause = (error as { cause?: unknown }).cause;

    if (cause instanceof Error) {
      return { code: (cause as { code?: unknown }).code as string | undefined, message: cause.message };
    }

    return { message: error.message };
  }

  return { message: String(error) };
}

function fetchHint(cause: FetchCause): string {
  if (cause.code === 'UNABLE_TO_VERIFY_LEAF_SIGNATURE' || cause.message.includes('certificate')) {
    return ' If this is local Laravel Herd HTTPS, run Node with --use-system-ca or set NODE_OPTIONS=--use-system-ca.';
  }

  if (cause.code === 'ENOTFOUND') {
    return ' Check MAAC_BASE_URL and make sure the hostname resolves from this process.';
  }

  return '';
}
