# MAACC Audit Integrity and Archive Runbook

**Control owner:** Security / Compliance

**Change owner:** Aminu Hussain
**Implementation baseline:** 2026-07-18

## Control design

Every application audit write goes through `AuditLedger`. A row lock on the tenant chain head allocates a monotonic sequence. The canonical event is linked to the previous signature and signed with HMAC-SHA256. The event, advanced chain head, and independently deliverable archive-outbox row commit in one database transaction.

The export manifest uses a separate export key. Its `rows_digest` is explicitly an unkeyed transport digest; authenticity is provided only by the HMAC `signature` and `signature_key_id`.

`DeliverAuditArchive` verifies the source signature before writing a deterministic, never-overwritten archive object. Production must configure the dedicated archive disk to an object-lock/WORM bucket or independently administered SIEM archive. An existing object is accepted only when its digest is identical.

## Production prerequisites

- Generate unrelated high-entropy `MAACC_AUDIT_CHAIN_KEY` and `MAACC_AUDIT_EXPORT_KEY` values in the enterprise secrets manager. Do not reuse `APP_KEY`, database credentials, or webhook secrets.
- Assign unique key IDs and retain retired verification keys for the signed retention window during a controlled rotation.
- Point `audit_archive` at an independently administered bucket with versioning, object lock in compliance mode, a retention period of at least `MAACC_AUDIT_ARCHIVE_RETENTION_DAYS`, deletion protection, access logging, and cross-account recovery access.
- Set `MAACC_AUDIT_ARCHIVE_DRIVER=s3` and `MAACC_AUDIT_ARCHIVE_IMMUTABLE_ENFORCED=true` only after the object-lock, retention, identity and deny-overwrite/delete evidence is approved. Enterprise production refuses local or unattested filesystem archival.
- Application database role: INSERT/SELECT on `audit_events`, SELECT/UPDATE on `audit_chain_heads`, and INSERT/SELECT/UPDATE on `audit_archive_outbox`; no direct UPDATE/DELETE on signed event fields. A separate retention role may delete only events proven archived and outside legal hold.
- Archive worker identity: create-object and read-object for verification; no overwrite/delete/bypass-governance permission.
- Security verifier identity: read-only database/archive/key metadata access; no application write permission.

The current production site is a demo with no real data. Keep `MAACC_ENTERPRISE_STATUS=non_enterprise` and `MAACC_REAL_SENSITIVE_DATA_ENABLED=false` until these external prerequisites and gates are evidenced.

## Monitoring and recovery

- `maacc:verify-audit --json` runs hourly and exits non-zero on invalid signatures/key IDs, broken links, reordering, early deletion, missing outbox rows, payload divergence, failed/pending archive delivery, missing archive objects, or chain-head mismatch.
- `maacc:maintain-runtime` runs every minute and requeues pending/failed archive outbox rows.
- Alert immediately on any verifier non-zero exit, archive outbox age beyond five minutes, failed archive delivery, WORM-policy drift, verifier-key access by the application identity, or archive restore failure.
- Preserve the verifier JSON, queue/job evidence, archive access log, and incident correlation ID in the release/incident record.

For recovery, stop audit-producing mutations if chain integrity is uncertain, snapshot the database and archive inventory, run the verifier, requeue only signature-valid outbox rows, and compare every deterministic object key and receipt. Never rewrite an existing archive object. Escalate any signature, sequence, key-ID, or object-content mismatch as a security incident.

## Legal hold and retention

`audit_events.legal_hold_until` prevents local pruning while active. Local rows are pruned only after successful independent archival, expiry of the governance retention window, and absence/expiry of legal hold. WORM retention and legal holds in the archive are authoritative and must be applied by the independent archive administrator.

## Required exercises

Before enterprise activation and at least quarterly:

1. Tamper with a non-production event copy and prove signature detection.
2. Remove/reorder a non-production row and prove sequence/link detection.
3. Deny archive writes and prove alerting, retry, and recovery.
4. Remove an archive test object and prove archive-loss detection and restore.
5. Attempt overwrite/delete with the application identity and prove denial.
6. Apply a legal hold, pass the retention boundary, and prove both local and WORM preservation.
7. Restore chain head, events, outbox, keys, and archive into an isolated environment and produce a valid independent verification report.
