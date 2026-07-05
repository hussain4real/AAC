/**
 * Verifies (and, for tests, produces) the HMAC-SHA256 signature MAACC sends on
 * every webhook delivery. The signature is computed over `{timestamp}.{body}`,
 * so a receiver can reject replays outside a tolerance window. This mirrors
 * MAACC's server-side signer exactly and is pinned by the shared contract
 * fixtures.
 */

import { createHmac, timingSafeEqual } from 'node:crypto';

/** Compute the hex HMAC-SHA256 signature for a payload at a timestamp. */
export function signWebhook(payload: string, timestamp: string, secret: string): string {
  return createHmac('sha256', secret).update(`${timestamp}.${payload}`).digest('hex');
}

/**
 * Verify a received `X-Maacc-Signature` against the raw request body, the
 * `X-Maacc-Webhook-Timestamp` header, and the endpoint's signing secret, within
 * the given clock-skew tolerance (in seconds). The signature may include the
 * `sha256=` prefix.
 */
export function verifyWebhook(
  payload: string,
  signature: string,
  timestamp: string,
  secret: string,
  toleranceSeconds = 300,
  now?: number,
): boolean {
  if (timestamp.trim() === '' || !/^-?\d+$/.test(timestamp.trim())) {
    return false;
  }

  const current = now ?? Math.floor(Date.now() / 1000);

  if (Math.abs(current - Number(timestamp)) > toleranceSeconds) {
    return false;
  }

  const expected = signWebhook(payload, timestamp, secret);
  const provided = signature.startsWith('sha256=') ? signature.slice(7) : signature;

  if (expected.length !== provided.length) {
    return false;
  }

  return timingSafeEqual(Buffer.from(expected), Buffer.from(provided));
}
