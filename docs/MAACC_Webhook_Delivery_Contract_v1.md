# MAACC Webhook Delivery Contract v1

**Status:** Approved implementation baseline

**Contract version:** 1.0.0

**Change owner:** Aminu Hussain
**Effective date:** 2026-07-18

This contract defines the behavior a production webhook consumer must implement. It is part of the frozen public API v1 baseline in `MAACC_Public_API_v1_Contract_ADR.md`.

## Delivery model

MAACC provides **at-least-once** delivery. A network timeout, worker crash after the receiver commits, manual replay, or recovery process can produce a duplicate. Consumers must not assume arrival order across concurrent workers.

Every attempt carries:

| Header | Meaning |
|---|---|
| `X-Maacc-Webhook-Event` | Versioned event name. |
| `X-Maacc-Webhook-Delivery` | Stable delivery UUID and the consumer idempotency key. It does not change on automatic retry or manual replay. |
| `X-Maacc-Webhook-Sequence` | Monotonic sequence within one endpoint. Use it to detect gaps or out-of-order arrival; do not reject an otherwise valid late delivery. |
| `X-Maacc-Webhook-Replay` | `0` initially; incremented for an explicit operator replay. |
| `X-Maacc-Webhook-Key-Version` | Endpoint signing-key version used for this delivery. |
| `X-Maacc-Webhook-Timestamp` | Unix timestamp included in the signature. |
| `X-Maacc-Signature` | `sha256=<hex HMAC>` over `<timestamp>.<raw request body>`. |

The delivery ID and event sequence are allocated in the same row-locked transaction as the outbox record. A per-endpoint deduplication key prevents duplicate runtime transitions from creating a second delivery.

## Required consumer algorithm

1. Read the raw body bytes before JSON decoding.
2. Reject a missing/invalid delivery ID, sequence, timestamp, or signature.
3. Verify HMAC-SHA256 with constant-time comparison and the configured tolerance (default 300 seconds).
4. In one durable transaction, insert the delivery ID into a table with a unique constraint and apply the event's business effect.
5. If the unique insert reports an existing delivery, skip all side effects and return the same successful 2xx acknowledgement.
6. Record the endpoint sequence. Alert on gaps or regressions, but accept valid late/out-of-order events.
7. Return 2xx only after the durable idempotency record and required side effects commit.

The PHP and TypeScript SDKs provide signature and delivery-metadata verifiers. The PHP CLI reference receiver demonstrates duplicate acknowledgement. Its in-memory set is illustrative; enterprise consumers must use durable shared storage.

## Retry, replay, ordering, and failure

- Automatic attempts use the same delivery ID, sequence, event, and body. The timestamp/signature may change on each attempt.
- Retries use bounded backoff and stop after the configured attempt limit. Failed deliveries remain visible and replayable.
- Explicit replay retains the delivery ID/sequence, increments the replay header, and signs with the current key version.
- A duplicate queued job cannot deliver a row already marked delivered. A crash after receiver commit but before MAACC records 2xx can still create a duplicate; the stable ID is the correctness control.
- Out-of-order delivery is allowed. Consumers reconcile by event semantics and endpoint sequence rather than arrival time.
- `maacc:maintain-runtime` releases stale delivery claims and requeues recoverable work. Scheduler execution is guarded by `withoutOverlapping` and `onOneServer`.

## Signing-key rotation

New deliveries use the new secret immediately. The immediately previous secret remains available only for the configured overlap (default 24 hours) so already-created deliveries can finish. After overlap, an old-version delivery fails closed. An explicit replay is rebound to the current version. Consumers must install the new secret before activating the rotated endpoint and accept both current and previous secrets only during the declared overlap.

## Retention and privacy

Delivery metadata is retained for 30 days by default, configurable by governance policy. MAACC retains the bounded event payload, delivery status, attempt count, response status, and controlled error; it does not retain arbitrary receiver response bodies. Retention cleanup operates only on terminal delivered/failed rows. Audit records describing endpoint and replay actions follow the independent audit/legal-hold policy.

## Compatibility evidence

- Server: outbox deduplication, monotonic sequencing, CAS claim, duplicate-job, rotation-overlap, replay, and header tests.
- PHP SDK/reference consumer: signature/tolerance and stable-delivery-ID duplicate tests.
- TypeScript SDK: signature/tolerance and `WebhookDeliveryVerifier` duplicate/sequence tests.
- Shared fixtures pin the HMAC algorithm and controlled error envelopes.
