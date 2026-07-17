# MAACC Phase 1 Decision Baseline v1

**Status:** Proposed — owner approval required
**Prepared:** 15 July 2026
**Scope:** ADR-P1-001 through ADR-P1-014
**Supersedes:** none; extends candidate architecture decisions AD-001–AD-010
**Required gate:** G0, with mapped portions of G1, G3, and G5

## Decision protocol

Each decision below is independently reviewable. Its status remains **Proposed** until the named decision owners record approval in the final table or an external signed decision system linked from that table. Approval selects the target contract; it does not claim that deferred implementation or operational evidence already exists.

### ADR-P1-001 — Ownership and tenant boundaries

**Status:** Proposed
**Owners:** Product/Platform Owner, Architecture, Security/IAM

MAACC is one centrally operated platform. A Team is the tenant security boundary; Applications belong to exactly one Team; Projects belong to exactly one Application and Team; agents, credentials, providers, tools, sources, connectors, runs, approvals, quotas, and audit records must resolve through those approved parents. Platform roles are global and separately governed; tenant membership never implies platform administration. Application teams retain ownership of business data and client-side authorization.

Cross-tenant attachment, lookup, manifest exposure, execution, export, or quota attribution must fail closed even when an identifier is known. Global resources require an explicit platform-owned classification and policy rather than a null tenant shortcut.

### ADR-P1-002 — Environments and promotion

**Status:** Proposed
**Owners:** Product/Platform Owner, Architecture, SRE, Security/IAM

The supported environment vocabulary is `development`, `sandbox`, `staging`, and `production`. Credentials, approvals, provider bindings, tool implementations, data policies, quotas, and audit provenance are environment-bound. Promotion creates or approves a new immutable target-environment version; it never re-labels or reuses a lower-environment credential or approval.

Production is fail-closed: no environment fallback, demo secret, local identity shortcut, unverified provider, or draft dependency may reach it. Sandbox is non-production and must not contain real sensitive data unless separately approved.

### ADR-P1-003 — OIDC, account linking, MFA, and entitlements

**Status:** Proposed
**Owners:** Security/IAM, Architecture, Product/Platform Owner

Tenant SSO uses OIDC authorization code plus PKCE with pinned discovery/JWKS and validation of signature, algorithm, issuer, audience, authorized party, state, nonce, expiry, and callback connection. Email-based automatic account linking is prohibited. An identity resolves only by exact `(issuer, subject)`, approved pre-provisioning, or a pre-authenticated MFA-backed linking ceremony.

Verified email and approved tenant/domain claims are mandatory when used for provisioning. Tenant IdPs cannot provision or inherit platform-admin access. SSO-sourced entitlements are authoritatively reconciled, including removals, without deleting independent human grants. Connection creation, testing, approval, activation, disablement, anomalies, outage, and break-glass use are audited. Privileged recovery requires phishing-resistant local MFA held by named custodians.

### ADR-P1-004 — Data classification, storage, masking, and exclusion

**Status:** Proposed
**Owners:** Privacy/Compliance, Security, Product/Data Owner

MAACC uses four handling tiers: Public, Internal, Confidential, and Restricted. Internal metadata may be retained within tenant authorization. Confidential prompts, responses, arguments, results, documents, and traces are masked at retained and outbound boundaries unless an approved purpose requires storage. Restricted identity and secret material is minimized, encrypted, never logged raw, and never exposed in browser props or public failures.

`exclude` means the value is never persisted or emitted in any duplicate copy. `mask` means every retained, queued, traced, audited, exported, webhook, cache, failed-job, and log representation is transformed before crossing the boundary. Transient execution state uses an encrypted tenant-scoped store with explicit TTL. A new sink is prohibited until it participates in the same policy and sentinel suite.

### ADR-P1-005 — Retention, deletion, legal hold, and audit archive

**Status:** Proposed
**Owners:** Privacy/Compliance, SRE, Security, Product/Data Owner

The current candidate defaults are prompts 90 days, responses 90 days, tool arguments 30 days, tool results 30 days, and operational audit 365 days, with stricter environment/classification overrides. These are maximum defaults pending data-owner approval, not permission to onboard sensitive data. Restricted payloads default to exclusion regardless of a numeric window.

Production requires category-specific deletion across all duplicate stores, measured deletion lag, legal-hold precedence, immutable or externally verifiable audit archival, backup-expiry behavior, restore-aware deletion, and approved exceptions. Until those controls and schedules are accepted, real sensitive-data onboarding remains disabled.

### ADR-P1-006 — Tool schema contract

**Status:** Proposed
**Owners:** Architecture, SDK Owner, Security, Product/Platform Owner

The public v1 tool contract targets JSON Schema 2020-12 for input and output, including nested objects, arrays/items, required fields, enums, bounds, formats, duplicate-key policy, and explicit `additionalProperties`. Arguments are validated, projected, and size-bounded before persistence or execution; outputs are bounded before parse or retention.

The current compact dialect is pre-v1 compatibility behavior. It may remain temporarily only with a documented adapter, fixture coverage, deprecation window, and no claim of full JSON Schema support.

### ADR-P1-007 — Run lifecycle and idempotency

**Status:** Proposed
**Owners:** Product/Platform Owner, Architecture, SDK Owner, SRE

The authoritative statuses are `queued`, `running`, `requires_tool`, `waiting_for_client`, `requires_approval`, `completed`, `failed`, `expired`, and `cancelled`. Terminal statuses are completed, failed, expired, and cancelled. Legal transitions are centrally enforced and every mutating client request carries idempotency provenance.

`requires_tool` identifies the model decision to invoke a tool; `waiting_for_client` identifies a durable client-tool pause after the request has been materialized. Public v1 must document both or expose a versioned compatibility projection. Retries, duplicate callbacks, cancellation, expiry, resume, and worker crashes must converge without duplicate tool effects or quota charges.

### ADR-P1-008 — Trusted caller context

**Status:** Proposed
**Owners:** Security, Architecture, SDK Owner, Privacy/Compliance

Caller context is a signed, minimized envelope issued or verified by the registered application. It contains a version, application/environment audience, opaque subject, optional department/role claims from an approved vocabulary, issued/expiry times, correlation ID, and nonce or request binding. Free-form caller strings are informational only and cannot authorize tools, data, reporting, or approvals.

MAACC validates the envelope, stores only policy-permitted claims, passes the minimized context consistently to PHP and TypeScript tool handlers, and prevents consuming users from escalating or substituting it. Raw upstream identity tokens are never forwarded as caller context.

### ADR-P1-009 — Publication, readiness, and four-eyes approval

**Status:** Proposed
**Owners:** Product/Platform Owner, Security, Release Manager, Engineering

One authoritative readiness gate covers publication, approval, manifest, playground, evaluation, runtime start/resume, and jobs. Approval binds an immutable configuration hash and is invalidated by any material prompt, model, tool, routing, source, connector, environment, credential, or policy change.

Production publication and high-risk mutations require two distinct people: requester and authorized approver. Only one pending request exists per subject/version; decisions are row-locked and idempotent. Credential/model changes are staged until approval. Disabled, suspended, archived, incompatible, or foreign-tenant dependencies fail closed at management and execution boundaries.

### ADR-P1-010 — Pricing currency and cost semantics

**Status:** Proposed
**Owners:** Product/Finance Owner, Architecture, SRE

Provider catalog rates and run cost estimates use USD as source currency, stored with currency code, rate unit, effective timestamp, source, and pricing-version identifier. Reports display source currency. Conversion to another currency is prohibited unless a governed rate source, rate timestamp, conversion version, and rounding policy are stored with the derived amount.

Cost is an estimate derived from measured usage and versioned catalog rates; it must not be represented as an invoiced provider amount. Unknown or stale rates display an explicit unavailable/stale state rather than zero.

### ADR-P1-011 — SDK compatibility and release ownership

**Status:** Proposed
**Owners:** SDK Owner, Architecture, Release Manager, Product/Platform Owner

PHP and TypeScript are supported SDKs for the first public contract; Python remains experimental and is not an enterprise dependency. Server, both supported SDKs, fixtures, reference applications, examples, and migration guide form one release unit.

The API contract uses semantic versioning. Breaking changes require a major version and migration path; supported deprecations publish `deprecated_in`, `removed_in`, and guide references before removal. CI blocks fixture drift. The release manager owns compatibility-window changes and records published artifact provenance.

### ADR-P1-012 — SLO, capacity, RPO, and RTO targets

**Status:** Proposed
**Owners:** Product/Platform Owner, SRE, Architecture

Candidate production targets for approval and load validation are: 99.9% monthly availability for authenticated API acceptance and console access; p95 under 500 ms for non-model authenticated API reads/writes at the approved load profile; p95 under 1 second to accept an asynchronous run; queue age p95 under 30 seconds; and error rate below 1% excluding controlled client/provider failures. Model/tool completion latency is measured separately by provider/tool class and does not hide platform overhead.

Candidate recovery objectives are RPO 15 minutes and RTO 60 minutes for relational configuration/governance data, with explicitly approved objectives for object documents, audit archive, cache/transient state, and external secrets. Final approval requires a capacity profile, error budget, alert thresholds, backup schedule, isolated restore, authenticated canary, and failover evidence. These numbers are recommendations until the owners sign them.

### ADR-P1-013 — External production services and trust boundaries

**Status:** Proposed
**Owners:** Architecture, SRE, Security, Privacy/Compliance

Production uses managed or equivalently controlled relational database, encrypted cache/session/rate-limit services, durable queues with supervised workers, object storage, external vault/KMS, independent Passport/signing keys, enterprise IAM, centralized telemetry/SIEM, and controlled outbound egress. Every service has tenant/data classification, encryption, least privilege, rotation, access attribution, monitoring, backup/recovery, residency, processor, and failure-mode evidence.

Database-encrypted secrets are development containment, not the target enterprise vault. Empty allowlists and unavailable security dependencies fail closed. Vendor selection remains a procurement/architecture decision, but the listed capabilities are mandatory.

### ADR-P1-014 — Repository, release, deployment, and rollback controls

**Status:** Proposed
**Owners:** Repository Owner, Release Manager, Security, SRE, Engineering Lead

Repository visibility requires an explicit owner decision and historical secret review. The default branch requires pull-request review, the non-mutating `enterprise-gate`, exact coverage gate, build, and applicable security checks. Workflow actions are SHA-pinned and permissions least-privilege. Required deployment configuration fails closed.

Production deployment consumes the exact successful main-branch commit as a signed or attestable immutable artifact, uses a protected production environment with named approval, pinned hosts/identities, migration/rollback policy, deep authenticated readiness, and post-deploy observation. Rollback restores the preceding compatible artifact without destroying audit evidence. Direct unreviewed production deployment and false-green no-op deployment are prohibited.

## Decision-to-gap traceability

| Decision   | Primary Phase 1 gap     | Later implementation/evidence dependency               |
| ---------- | ----------------------- | ------------------------------------------------------ |
| ADR-P1-001 | ER-01                   | Phase 2 object/field RBAC matrix                       |
| ADR-P1-002 | G0 environment baseline | Phase 2 versioned promotion contract                   |
| ADR-P1-003 | ER-02                   | Target IdP, SIEM, outage, and custodian evidence       |
| ADR-P1-004 | ER-03/ER-05             | External sink and target worker evidence               |
| ADR-P1-005 | G3 baseline             | Immutable archive, legal hold, deletion/restore proof  |
| ADR-P1-006 | EC-02–EC-04             | Phase 2 public schema implementation                   |
| ADR-P1-007 | ER-04/ER-09             | Phase 2 concurrency and compatibility closure          |
| ADR-P1-008 | FR-053/EC-26            | Phase 2 signed context implementation                  |
| ADR-P1-009 | ER-04                   | Hosted race/release evidence                           |
| ADR-P1-010 | FR-016/FR-047           | Phase 3 reporting reconciliation                       |
| ADR-P1-011 | ER-08                   | Phase 2 public v1 release unit                         |
| ADR-P1-012 | G0/G6/G7                | Phase 4 load, restore, failover, and observation       |
| ADR-P1-013 | ER-15/G1/G3/G7          | Phase 4 platform and operational evidence              |
| ADR-P1-014 | ER-15/G5                | Repository policy, artifact, deploy, rollback evidence |

## Approval record

| Role                     | Named approver | Decisions                   | Decision  | Date      | Signature/reference | Conditions or residual risk |
| ------------------------ | -------------- | --------------------------- | --------- | --------- | ------------------- | --------------------------- |
| Product/Platform Owner   | _Pending_      | 001, 002, 007, 009, 012     | _Pending_ | _Pending_ | _Pending_           | _Pending_                   |
| Architecture             | _Pending_      | 001–014                     | _Pending_ | _Pending_ | _Pending_           | _Pending_                   |
| Security/IAM             | _Pending_      | 001–009, 013, 014           | _Pending_ | _Pending_ | _Pending_           | _Pending_                   |
| Privacy/Compliance       | _Pending_      | 004, 005, 008, 010, 013     | _Pending_ | _Pending_ | _Pending_           | _Pending_                   |
| SRE/Operations           | _Pending_      | 002, 005, 007, 010, 012–014 | _Pending_ | _Pending_ | _Pending_           | _Pending_                   |
| SDK Owner                | _Pending_      | 006–008, 011                | _Pending_ | _Pending_ | _Pending_           | _Pending_                   |
| Release/Repository Owner | _Pending_      | 009, 011, 014               | _Pending_ | _Pending_ | _Pending_           | _Pending_                   |

No blank or Proposed row may be interpreted as consent. Rejection must name the replacement decision and owner. Until the required decisions are signed and linked, the BRS/ADR checklist item and G0 remain open.
