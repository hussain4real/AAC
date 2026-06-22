/**
 * The HTTP abstraction the SDK depends on. The default {@link fetchTransport}
 * uses the global `fetch` (Node 18+, Deno, browsers); tests and special runtimes
 * can supply their own.
 */

import { TransportError } from './errors.ts';

export interface HttpRequest {
  method: string;
  url: string;
  headers: Record<string, string>;
  body?: string;
}

export interface HttpResponse {
  status: number;
  body: string;
}

export type Transport = (request: HttpRequest) => Promise<HttpResponse>;

/** The default transport, built on the global `fetch`. */
export const fetchTransport: Transport = async (request) => {
  let response: Response;

  try {
    response = await fetch(request.url, {
      method: request.method,
      headers: request.headers,
      body: request.body,
    });
  } catch (error) {
    throw TransportError.fromFetchError(request.url, error);
  }

  return { status: response.status, body: await response.text() };
};
