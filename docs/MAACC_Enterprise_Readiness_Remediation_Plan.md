# MAACC Enterprise Readiness Remediation Plan

## Source Materials Reviewed

- `docs/MAACC_Enterprise_Readiness_Review_2026-07-11.md`
- `docs/MAACC_BRS(1).md`
- `docs/MAACC_Architecture_Document.md`
- `docs/MAACC_Phased_Implementation_Plan.md`
- `graphify-out/GRAPH_REPORT.md`
- `graphify-out/graph.json`
- `docs/enterprise-readiness-evidence/`

## Overall Remediation Goal

Close every release blocker, readiness gap, BRS shortfall, UI/UX defect, logic edge case, and missing enterprise proof identified in the 11 July 2026 readiness review, then progress MAACC from an internal engineering/demo candidate to a controlled real-data pilot and, after an agreed observation period, an enterprise production release.

This plan is the execution companion to the [MAACC Enterprise Readiness and BRS Conformance Review](MAACC_Enterprise_Readiness_Review_2026-07-11.md). The review remains the evidence and decision record; this document is the four-phase delivery ledger. The historical [MAACC Phased Implementation Plan](MAACC_Phased_Implementation_Plan.md) remains useful implementation history, but a historical “Complete” marker does not satisfy this remediation plan unless the control is re-proven against the readiness review's exit gates.

## Program Baseline and Rules

- **Current decision:** NO-GO for enterprise production or a real-data pilot.
- **Baseline tally:** 31 of 56 functional requirements Implemented, 23 Partial, and 2 Not Found/Deferred; 26 of 44 Must Haves Implemented, 16 Partial, and 2 Not Found/Deferred.
- **Scope:** all `ER-01`–`ER-15`, all `EC-01`–`EC-40`, every partial or missing BRS requirement, all NFR proof gaps, the mandatory verification backlog, and enterprise gates `G0`–`G8`.
- **Status model:** checklist items remain open until implementation, automated verification, operational proof, and required acceptance are all linked. Code presence or line coverage alone is not completion.
- **Traceability:** every pull request must identify the ER/EC/BRS IDs it closes, tests added, operational evidence affected, and any residual risk.
- **Change control:** scope changes require an approved BRS/ADR update and must not silently convert a release blocker into accepted behavior.
- **Regression rule:** already-implemented BRS behavior and public SDK/runtime interfaces must remain protected unless a versioned migration is approved.
- **Release rule:** any explicit no-go condition in the readiness review keeps the release at NO-GO regardless of aggregate progress.

## Delivery Model

| Phase | Outcome                                                                 | Indicative timing                         | Primary review work packages | Gate to advance                                                                                                                               |
| ----- | ----------------------------------------------------------------------- | ----------------------------------------- | ---------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------- |
| 1     | Contain exposure and repair critical trust boundaries                   | Days 0–5 containment; Weeks 1–3 hardening | WP-0–WP-4 plus ER-15         | No uncontrolled P0 path; approved baseline; adversarial isolation, OIDC, data-sentinel, provider-key, readiness, and aggregate-CI tests green |
| 2     | Make governance, contracts, egress, and runtime lifecycle authoritative | Weeks 3–6                                 | WP-5–WP-7                    | Role/object matrix, versioned SDK contract, SSRF suite, concurrency/idempotency suite, and audit-integrity controls green                     |
| 3     | Make the console trustworthy, scalable, responsive, and accessible      | Weeks 5–9                                 | WP-8–WP-9                    | No false/dead high-risk controls; target-volume budgets and browser/WCAG gates green                                                          |
| 4     | Prove production operations and complete enterprise acceptance          | Weeks 8–12+, including pilot observation  | WP-10–WP-11                  | Gates G0–G8 signed; rollback/restore/game-day/load/pilot evidence accepted; no explicit no-go condition remains                               |

The timing assumes the staffing model in the readiness review: two backend engineers, two frontend engineers, one QA/SDET, part-time Security/IAM and SRE capacity, and named Product, Privacy/Compliance, and Accessibility approvers. Phase 4 platform preparation may start earlier, but its acceptance cannot finish before Phases 1–3 exit.

## Phase 1: Containment, Baseline Decisions, and Critical Trust Boundaries

> **Status: 🟨 Core engineering controls implemented and locally verified — phase acceptance remains blocked by controlled-environment evidence and named gate approvals. Enterprise release and real-data onboarding remain NO-GO. See the [Phase 1 engineering evidence](enterprise-readiness-evidence/Phase_1_Engineering_Evidence_2026-07-15.md).**

### Goal

Remove the immediate account-takeover, cross-tenant, credential-isolation, sensitive-data, publication-governance, and red-CI paths; freeze the enterprise contract in approved decisions; and create a safe foundation for all later work.

### Checklist

#### Immediate containment and governance baseline

- [x] Freeze onboarding of new tenants and real sensitive data; publish the current non-enterprise status and identify an accountable incident/change owner. Registration and tenant creation fail closed, the persistent UI notice identifies the non-enterprise state, and the 15 July 2026 owner attestation classifies the current production deployment as a synthetic demo with no real data. Real sensitive-data onboarding remains prohibited and Aminu Hussain is the accountable change owner.
- [ ] Disable tenant-created SSO connection activation, remove the global cross-tenant connection list, audit privileged `sso_identities` and sessions, and revoke suspicious links/sessions. The controls and [read-only audit command/local result](enterprise-readiness-evidence/Phase_1_Privileged_SSO_Access_Audit_2026-07-15.md) are implemented; real-environment review, revocation evidence, and Security/IAM sign-off remain required.
- [ ] Decide whether public repository visibility is approved. If not, make it private and complete a credential, signing-key, webhook-secret, SSO-secret, deployment-key, session, and Git-history exposure review and rotation. A [read-only repository/history inventory](enterprise-readiness-evidence/Phase_1_Repository_Exposure_Review_2026-07-15.md) found no open GitHub alert or high-confidence production credential, but owner approval, full organizational scanning, and rotation/revocation evidence remain required.
- [x] Run a read-only tenant-association integrity scan, quarantine mismatches, and record any necessary remediation before credential rotation or runtime re-enable.
- [x] Remove ordinary agent create/update access to privileged publication statuses and add fail-closed guards for suspended applications and mismatched runtime relationships.
- [x] Repair the known formatting failures, run `composer ci:check` from a clean checkout, and make the non-mutating aggregate gate a required release signal. Local aggregate CI and exact 100% coverage pass; [hosted enterprise-gate run 29438741125](https://github.com/hussain4real/AAC/actions/runs/29438741125) passed on PHP 8.4 and 8.5 from a frozen clean checkout, including production build, exact coverage, and tracked-file immutability. The hardened workflow preserves the active `main` ruleset's required contexts, and deployment waits for its success. Any stronger review/up-to-date policy remains a Repository Owner decision tracked under repository exposure.
- [ ] Approve BRS v1 and the required ADRs for ownership, environments, OIDC/account linking, data classification/storage, retention/legal hold, schemas, run statuses, caller context, approval rules, currency, SDK compatibility, SLO/RPO/RTO, external platform services, and release controls. The version-locked [BRS approval baseline](MAACC_BRS_v1_Approval_Baseline.md) and [14-decision ADR package](MAACC_Phase_1_Decision_Baseline_v1.md) are prepared; named owner decisions and signatures remain required.
- [x] Produce an updated [threat model, data inventory, trust-boundary map, risk register, control-owner and evidence register](enterprise-readiness-evidence/MAACC_Phase_1_Threat_Model_and_Control_Register_v1.md). Formal G0/G1/G3 review and residual-risk acceptance remain open acceptance criteria.

#### Tenant and parent ownership invariants — ER-01

- [x] Centralize same-team, approved-parent, project, environment, active-state, and assignment eligibility checks in domain services used by HTTP, console, API, jobs, imports, and runtime paths.
- [x] Scope every relationship-bearing validation query and resolved model to the authorized tenant and parent before persistence.
- [x] Authorize creation and mutation against resolved parent objects, not only a model class or current-team flag.
- [x] Enforce tool, connector, data source, implementation, model, routing, approval subject, and quota subject ownership at both management and runtime boundaries.
- [x] Add transactions, row locks, composite constraints, or invariant triggers where practical; document and test every invariant that must remain application-enforced.
- [x] Make legacy/null role data defensive so inconsistent imports cannot crash governance surfaces.
- [x] Add a [two-tenant adversarial matrix](enterprise-readiness-evidence/Phase_1_Two_Tenant_Adversarial_Matrix_2026-07-15.md) for create, update, import, manifest, publish, execute, export, and direct-object access paths.

#### Standards-compliant identity and account linking — ER-02, EC-17–EC-19

- [x] Restrict SSO connection creation and activation to global Security/IAM administrators with separate, audited approval and test-before-enable.
- [x] Implement OIDC authorization code with PKCE using pinned discovery/JWKS and signed ID-token validation.
- [x] Validate issuer, audience, authorized party, algorithm, expiry, nonce, state, and callback connection identity in one single-use transaction.
- [x] Require verified email and approved tenant/domain claims where email participates in provisioning.
- [x] Remove automatic linking of an external subject to an existing global user by email; require exact `(issuer, subject)`, approved pre-provisioning, or a pre-authenticated MFA-backed linking ceremony.
- [x] Prevent tenant IdPs from provisioning, linking, or inheriting global platform-administrator access.
- [x] Reconcile SSO-sourced project/platform entitlements authoritatively, including removals, while preserving independent human grants.
- [x] Add tenant-scoped discovery, dedicated throttling, durable tenant-scoped anomaly alerts, staged activation, controlled IdP-outage behavior, and audited time-boxed break-glass recovery. Target-environment SIEM/on-call delivery, local-MFA custody, and outage exercise remain open operational evidence.
- [x] Add replay, mix-up, unsigned token, wrong issuer/audience/nonce/expiry, unverified email, privileged-email collision, connection mismatch, group removal, and rate-limit tests.

#### Data and provider credential isolation — ER-03, ER-05, EC-01

- [x] Separate transient execution state from retained audit records and store transient state only in a short-lived encrypted tenant-scoped store with explicit TTL.
- [x] Apply the effective policy before every database, state, trace, audit, queue, failed-job, cache, webhook, export, log, browser-prop, and error boundary.
- [x] Make `exclude` mean never persisted or emitted; make masking/classification consistent across all duplicate copies.
- [x] Redact provider, tool, connector, database, and validation exceptions into stable public codes plus correlation IDs.
- [x] Replace process-global AI-provider configuration mutation with request-scoped provider clients and credentials.
- [x] Where upstream mutation is unavoidable, snapshot and restore configuration in `finally`, clear provider instances before and after every branch, and cover null-key, exception, retry, and failover paths. _(Superseded: MAACC no longer mutates upstream global provider configuration.)_
- [x] Reserve environment fallback for an explicitly platform-owned provider; require vault-bound credentials for tenant-owned provider billing boundaries.
- [x] Review all long-lived workers, singletons, caches, and retry payloads for tenant state leakage.
- [x] Add recursive sentinel tests and sequential/concurrent A→B credential-isolation tests for queue and Octane-style workers.

#### One authoritative readiness state machine — ER-04

- [x] Implement a single `AgentReadinessGate` used by direct publish, approval, manifest, playground, evaluation, runtime start, resume, and jobs.
- [x] Require same-tenant/project-approved models, correct environment, active dependencies, compatible implementations, required approvals, evaluations, safety policy, and current immutable configuration at every gate.
- [x] Define and enforce legal status transitions; remove privileged statuses from ordinary request payloads.
- [x] Bind approval to an immutable configuration/version hash and invalidate it after any material prompt, tool, model, routing, source, connector, environment, or credential change.
- [x] Enforce four-eyes separation, one pending request per subject/version, row-locked decisions, and idempotent duplicate handling.
- [x] Stage production credential and high-risk mutations until approval instead of approving an already-applied change.
- [x] Reject suspended/archived applications and inactive tools/models/connectors/sources before manifest exposure and again before execution.
- [x] Add concurrency, self-approval, stale-approval, double-submit, material-change, disabled-dependency, and bypass-path tests.

### Deliverables

- Approved BRS v1, ADR set, threat model, data inventory, risk register, and control-owner register.
- Containment record for SSO, repository visibility, onboarding, identity audit, and any detected tenant mismatch.
- Shared tenant/parent invariant services and data-integrity scanner.
- Standards-compliant OIDC integration and audited identity-linking/provisioning workflow.
- Governed transient-state/data-redaction pipeline and request-scoped provider credential handling.
- Unified, immutable-version-bound readiness and approval state machine.
- Green clean-checkout aggregate CI and a required hosted engineering gate.

### Acceptance Criteria

- No foreign-tenant application, project, provider, tool, source, connector, implementation, route member, approval subject, or quota subject can be attached, exposed, manifested, or executed.
- An untrusted tenant IdP cannot authenticate, link, mutate, or inherit an existing user or platform role; all OIDC negative tests pass.
- A restricted/confidential sentinel is absent from every prohibited persistence and outbound surface immediately after success, failure, retry, and retention processing.
- Sequential and concurrent tenants never observe, use, or are billed through another tenant's provider credential.
- No route, request payload, job, approval action, manifest, playground, or runtime path bypasses the authoritative readiness state machine.
- BRS/ADR baseline and threat model are approved, integrity scans are complete, and `composer ci:check` plus hosted required checks pass from a clean checkout.
- Enterprise gates **G0**, the Phase 1 portion of **G1/G3**, and the immediate engineering portion of **G5** have signed evidence.

## Phase 2: Authoritative Access, Contracts, Egress, Runtime, and Audit Controls

> **Status: ⬜ Not started — depends on the Phase 1 ownership, identity, data, and readiness foundations.**

### Goal

Make least-privilege authorization, project administration, public SDK/runtime contracts, outbound-network policy, concurrency controls, lifecycle recovery, and audit evidence consistent across every entry point and execution mode.

### Checklist

#### Authoritative RBAC and scoped data — ER-06

- [ ] Publish a route/action/object/field/export/navigation capability matrix for every tenant and platform role.
- [ ] Replace production personas and `localStorage` authorization behavior with server-issued capabilities.
- [ ] Implement audited project-member assignment, revocation, expiry, role change, and access certification through UI and API.
- [ ] Enforce the documented cross-tenant remit of each platform role in policies, query scopes, resources, exports, and direct URLs.
- [ ] Deliver only data authorized for the actor, tenant, project, environment, and permission; hidden UI must never receive unauthorized props.
- [ ] Add page-prop, direct-URL, action, field, export, current-team switch, and object-level tests for every role/no-role combination.

#### Unified outbound request security — ER-07, EC-20

- [ ] Route webhooks, SSO discovery/token/userinfo, remote tools, MCP connectors, knowledge/document fetchers, and future outbound HTTP through one hardened policy.
- [ ] Require HTTPS in production and approved destination domains where feasible.
- [ ] Resolve all destination IPs and deny loopback, private, link-local, multicast, metadata, and disallowed port ranges for IPv4 and IPv6.
- [ ] Revalidate and pin destinations through redirects, prevent DNS rebinding, strip sensitive headers on host changes, and enforce infrastructure egress controls.
- [ ] Add safe destination verification and test delivery before activation.
- [ ] Add SSRF tests for literals, alternate numeric encodings, userinfo, DNS-to-private, rebinding, redirects, IPv6, ports, and metadata endpoints.

#### Versioned BRS, API, SDK, and tool contract — ER-08, FR-053, EC-02–EC-04, EC-26

- [ ] Freeze the public v1 contract in an ADR and resolve every BRS/architecture/server/SDK disagreement.
- [ ] Adopt JSON Schema 2020-12 or formally version the compact dialect, including nested objects, arrays/items, enum/bounds/formats, duplicate-key handling, and `additionalProperties` policy.
- [ ] Validate, project, and size-check arguments before persistence or execution for every tool mode; apply uniform bounded reads and output limits before parse/persist.
- [ ] Add a signed, minimized caller-context envelope to the API, PHP SDK, TypeScript SDK, tool context, fixtures, responses, and compatibility suite.
- [ ] Resolve and version `requires_tool` versus `waiting_for_client` semantics with a documented migration/deprecation window.
- [ ] Snapshot every execution-relevant prompt, model, tool, sensitivity, routing, approval, policy, environment, and version field for reproducible rollback.
- [ ] Correct manifest project/environment/scope/status rules, including global tools and Required/Not Required/Disabled derivation.
- [ ] Support governed draft-agent testing without creating a publication bypass.
- [ ] Update BRS, architecture, fixtures, SDKs, reference applications, examples, compatibility policy, and migration guide in one coordinated change.

#### Concurrency-safe runtime lifecycle — ER-09, EC-05–EC-14, EC-21, EC-23–EC-25, EC-39

- [ ] Add application-scoped idempotency keys and request hashes for run creation with stable replay and conflict behavior.
- [ ] Use row-locked compare-and-swap or equivalent claims so duplicate jobs/tool results can cause only one execution and transition.
- [ ] Align job timeout below queue visibility timeout with margin; define tries, backoff, uniqueness, failure repair, poison-message handling, and stuck-run recovery.
- [ ] Replace `max + 1` sequence allocation with locked counters or unique constraints plus conflict retry.
- [ ] Reserve quotas atomically against the routed provider and reconcile actual usage after completion/failure.
- [ ] Add proactive waiting-run expiry, stale-approval/webhook/token/failed-job cleanup, exactly-once terminal events, and monitored scheduler overlap controls.
- [ ] Add weighted per-client API rate and concurrency limits, stream caps, gateway body/header/time limits, and backpressure.
- [ ] Enforce database statement timeouts and cleanup; treat never-refreshed sources as stale when freshness is required.
- [ ] Move upload/parsing to isolated asynchronous workers with MIME/magic validation, malware quarantine, decompression/page/text/chunk caps, and no long database transaction.
- [ ] Persist initiating user/application, correlation ID, and policy version before dispatch so background work has authoritative provenance.
- [ ] Add race, crash/retry, duplicate delivery, queue backlog, provider failure, tool timeout, stream saturation, upload bomb, and partial-failure tests.

#### Verifiable audit and webhook semantics — EC-15, EC-16, EC-38

- [ ] Replace a recomputable checksum claim with HMAC/asymmetric signatures backed by independently managed keys, or rename the control accurately.
- [ ] Deliver append-only/WORM or SIEM-backed audit archival, protected database roles, legal hold, hash chaining/signature as required, and monitored outbox delivery.
- [ ] Detect tampering, reordering, deletion, exporter-key misuse, archive failure, and recovery failure.
- [ ] Document webhook delivery-ID idempotency, duplicate/out-of-order delivery, signature tolerance, key-rotation overlap, replay behavior, and retention.
- [ ] Add independently verified audit exports and consumer compatibility tests.

### Deliverables

- Authoritative tenant/platform capability matrix and project-membership administration.
- Shared outbound HTTP/egress guard plus infrastructure policy and SSRF regression suite.
- Frozen, versioned API/SDK/schema/status/caller-context/manifest contract across PHP and TypeScript.
- Atomic quota, idempotency, job, transition, expiry, scheduler, rate/concurrency, and provenance controls.
- Governed asynchronous knowledge ingestion and safe public error contract.
- Verifiable, immutable audit archival and complete webhook delivery contract.

### Acceptance Criteria

- Every page, prop, object, field, action, export, direct URL, and navigation item produces the same least-privilege result for the same actor.
- No tenant-controlled URL can make MAACC reach a disallowed destination, including through DNS resolution, redirects, alternate encodings, or IPv6.
- BRS, architecture, server fixtures, both SDKs, reference apps, examples, statuses, schemas, caller context, and manifest semantics describe one versioned contract.
- Duplicate requests, jobs, streams, approvals, or tool results cannot double-execute, double-spend, exceed hard quotas, or create conflicting terminal states.
- Abandoned work expires without client traffic, failed work has deterministic recovery, and scheduled tasks cannot silently overlap or stop.
- Audit tampering/deletion is independently detectable, archives are recoverable, and webhook consumers can safely process at-least-once delivery.
- Enterprise gates **G1**, **G2**, and the control portions of **G3/G6** have signed evidence for this phase.

## Phase 3: Trusted, Scalable, Responsive, and Accessible Product Experience

> **Status: ⬜ Not started — frontend foundation work may overlap Phase 2, but final acceptance requires stable authoritative contracts and capabilities.**

### Goal

Ensure that console data, metrics, controls, workflows, responsive behavior, and accessibility accurately reflect authoritative backend state and continue to work at enterprise tenant volumes and non-happy-path conditions.

### Checklist

#### Page data architecture and scale — ER-11, EC-22

- [ ] Replace global shared console corpora with page-scoped authorized resources and query objects.
- [ ] Add cursor pagination and server-side filtering/search/sort for runs, traces, audits, tools, approvals, webhooks, grants, and other high-volume collections.
- [ ] Use Inertia deferred/optional props, partial reloads, and explicit skeleton/empty/error states for secondary data.
- [ ] Replace global cache invalidation with tenant-scoped tags/versioning and event-driven aggregates.
- [ ] Add compound indexes based on measured query plans and prevent N+1/query-count regressions.
- [ ] Establish and test query-count, response-byte, p50/p95/p99 server, browser-render, memory, and row-count budgets at 10k/100k-run datasets.

#### Accurate reporting and operational truth — ER-10, EC-28

- [ ] Store pricing currency with rates and runs; display source currency or use a governed versioned conversion rate and timestamp.
- [ ] Derive provider, run, cost, usage, and failure rollups from authoritative facts/materialized aggregates rather than fixture counters.
- [ ] Define timestamp/timezone rules and reconcile run, trace, tool, queue, and terminal events atomically.
- [ ] Separate application metrics from platform/dependency health and show source, freshness, and calculation metadata.
- [ ] Add user/department reporting from the trusted caller context without exposing prohibited personal data.
- [ ] Handle zero/one-point chart datasets without divide-by-zero, NaN, or invalid SVG output.
- [ ] Add metric reconciliation tests against known datasets and independent finance/operations review.

#### Authoritative actions and recoverable workflows — ER-12, EC-27, EC-29–EC-34

- [ ] Implement or remove every visible action, export, date filter, trace copy, SDK re-validation, support/documentation link, search, and environment selector.
- [ ] Persist every Create Agent safety/guardrail/approval/logging field or remove it; validate each step on client and server and clear stale dependent selections.
- [ ] Generate API endpoints and examples from route/contract metadata rather than hard-coded strings.
- [ ] Standardize mutation states across confirmation, processing, success, recoverable error, retry, stale conflict, authorization loss, rate limit, and duplicate click.
- [ ] Make one-time credential/webhook-secret dialogs non-dismissible until explicit acknowledgement; await clipboard results and provide a manual fallback.
- [ ] Add dirty-form/navigation protection, safe browser back/refresh behavior, request cancellation/identity for Playground, and timezone-aware absolute timestamps.
- [ ] Detect duplicate/empty schema keys with stable row identity and exact error location.
- [ ] Add accessible loading, empty, not-found, forbidden, offline, partial, stale, very-large, and retry states with correct 403/404 boundaries.
- [ ] Make approval rationale, stale blockers, and 403/409/422/429/network/server failures visible without false success.

#### Responsive and accessible foundations — ER-13

- [ ] Rebuild shared primitives with native/Radix semantics, accessible names, associated errors, live regions, focus visibility/restoration, skip link, keyboard row/card links, arrow-key tabs, and robust menus/dialogs.
- [ ] Build a `100dvh` responsive shell with drawer/collapsible navigation, breakpoint-aware grids, stacked actions/filters, modal reflow, and mobile alternatives for dense tables.
- [ ] Replace failing color pairs with WCAG 2.2 AA-tested tokens in light and dark modes.
- [ ] Support 320–1440px, split-screen, large text, 200% zoom, reduced motion, touch targets, keyboard-only operation, and VoiceOver core journeys.
- [ ] Add automated axe/contrast, component/browser interaction, and screenshot regression gates at 320, 375, 768, 1024, and 1440px.

#### Product identity and maintainable frontend — EC-35, EC-36, EC-40

- [ ] Replace the stock Laravel root with the approved MAACC entry/redirect and correct application titles, package metadata, release/environment identity, privacy, legal, security, and support links.
- [ ] Self-host approved fonts or use a safe system stack; close CSP/privacy/offline and CSS import-order concerns.
- [ ] Split the 5,805-line public page into typed, linted components and bring it under visual, performance, and accessibility tests.
- [ ] Make demo tenant identity, slug, branding, timestamps, and telemetry internally coherent and prevent fixture labels from reaching production.
- [ ] Add representative authenticated-route asset/manifest readiness smoke so `/up` cannot be green while product routes fail.

### Deliverables

- Authorized page-scoped data resources, cursor pagination, server filters, indexes, and tenant-scoped caching.
- Reconciled currency, usage, cost, latency, timestamp, and dependency-health reporting.
- Fully authoritative console controls with standardized mutation, recovery, secret, dirty-state, and error handling.
- Responsive MAACC shell and repaired accessible component primitives.
- Repeatable browser, axe, contrast, keyboard, screenshot, and high-volume performance suites.
- Branded, typed, tested public entry and coherent non-production fixtures.

### Acceptance Criteria

- Representative high-volume pages meet approved p95 query, response-size, render, memory, and pagination budgets without unauthorized or unbounded data.
- Finance, usage, latency, status, and health figures reconcile to source facts and expose their source/freshness.
- Every primary and destructive action is authoritative and browser-tested across success, validation, authorization, conflict, rate-limit, network, server, and duplicate-submit states.
- A new empty tenant can complete application → project → member → agent → tool → SDK report → governed draft test → approval/publish → production invocation without hidden fixture assumptions.
- Core workflows meet WCAG 2.2 AA, keyboard and VoiceOver acceptance, and remain usable at 320–1440px, 200% zoom, reduced motion, and light/dark themes.
- No dead or false high-risk control remains; asset/readiness smoke proves representative authenticated routes from the promoted artifact.
- Enterprise gate **G4** and the product/performance portions of **G5/G6** have signed evidence.

## Phase 4: Production Platform Proof, Enterprise Acceptance, and Controlled Pilot

> **Status: ⬜ Not started — production release remains blocked until every phase and gate below is evidenced.**

### Goal

Build, secure, operate, recover, and independently validate a production-grade MAACC release; complete every enterprise gate; and run a limited monitored external-application pilot before general production approval.

### Checklist

#### Deterministic and governed software supply chain — ER-14

- [ ] Use frozen dependency installs and non-mutating lint/format checks that fail on any generated diff.
- [ ] Require appropriate approvals, CODEOWNERS/security review for critical paths, resolved review threads, strict current checks, aggregate CI, and protected production-environment approval.
- [ ] Reduce workflow permissions to least privilege and fail deployment when required secrets/configuration are missing.
- [ ] Add Composer/npm update coverage, dependency audit, secret scanning/push protection, SAST, SBOM, provenance, and artifact signing according to approved policy.
- [ ] Build once and promote the same immutable, signed artifact through protected environments with release identity and migration compatibility checks.
- [ ] Implement canary/blue-green or equivalent progressive activation, atomic web/worker revision control, automatic failure detection, and tested rollback.

#### Hardened production configuration and secrets — EC-37

- [ ] Produce an environment-specific production configuration matrix and fail startup on debug, cookie/session, TLS, database, cache, queue, mail, filesystem, key, proxy, or logging combinations classified unsafe.
- [ ] Bind and prove external KMS/vault, independent signing keys, least-privilege access, rotation without redeploy, access attribution, alerting, and recovery/escrow controls.
- [ ] Enforce production network segmentation, egress firewall, WAF/gateway limits, secure headers/CSP, certificate lifecycle, and protected administrative access.
- [ ] Verify no demo credentials, starter-kit metadata, fixture telemetry, local storage defaults, or development fallbacks are active in production.

#### Availability, observability, backup, restore, and disaster recovery

- [ ] Approve measurable SLO/SLI, error budgets, p50/p95/p99 latency/throughput, capacity targets, RPO/RTO, retention/deletion lag, and escalation thresholds.
- [ ] Split liveness, readiness, and deep dependency health for database, cache, queue/workers, scheduler, storage, Passport keys, vault, providers, webhooks, and certificate expiry.
- [ ] Deploy structured logs, traces, metrics, correlation IDs, SIEM integration, actionable alerts, dashboards, on-call ownership, and runbooks.
- [ ] Prove redundant web, queue, cache, database, storage, and scheduler topology with controlled failover and noisy-neighbor protection.
- [ ] Back up relational data, documents, application/key history, Passport material, vault references, configuration, and audit archives; monitor backup validity and restore lag.
- [ ] Restore into an isolated clean environment and execute an authenticated canary within approved RPO/RTO.
- [ ] Run load, soak, failover, chaos, queue-backlog, provider-degradation, webhook-backlog, key-rotation, rollback, restore, and incident-escalation exercises.

#### Independent enterprise verification and pilot

- [ ] Re-run the complete mandatory security/authorization, data/compliance, runtime/contract, UI/accessibility, and operations/resilience backlogs from the readiness review.
- [ ] Build a final FR-001–FR-056/NFR traceability ledger with automated evidence for every Must Have or an explicitly approved BRS change/risk acceptance.
- [ ] Obtain independent Security/IAM review, privacy/compliance approval, accessibility acceptance, architecture/SRE approval, and engineering-quality sign-off.
- [ ] Confirm there is no open Critical/High attack path and no explicit no-go condition remains.
- [ ] Execute a limited external-application pilot through the full BRS workflow using an agreed tenant/application/data-class cohort.
- [ ] Monitor SLOs, tenant/data boundaries, costs, support incidents, user outcomes, and rollback triggers for the agreed observation window.
- [ ] Record business, security, support, operations, and product acceptance before the go-live board decides general production release.

### Deliverables

- Protected deterministic CI/CD, immutable signed artifacts, provenance/SBOM, progressive delivery, and tested rollback.
- Hardened production configuration and external KMS/vault implementation.
- Dependency-aware readiness, full observability/SIEM, SLO dashboards, on-call runbooks, and incident controls.
- Proven HA/capacity, backup/restore, RPO/RTO, failover, and disaster-recovery evidence.
- Final BRS/NFR traceability and independent security, privacy, accessibility, engineering, architecture, and operations sign-offs.
- Controlled-pilot report and go-live board decision record.

### Acceptance Criteria

- A signed immutable release can be promoted, identified, observed, rolled back, and restored under approved controls without compiling or mutating production in place.
- Node, queue, cache, database, storage, provider, scheduler, and webhook failures produce bounded behavior, preserve required availability, and trigger actionable alerts.
- Load/soak tests meet approved p95/p99 latency, throughput, error, queue-age, memory, database, and cost budgets at target concurrency and data volume.
- A clean-environment restore recovers all required data/key/reference/audit assets and completes an authenticated canary within signed RPO/RTO.
- Every Must Have is implemented with automated acceptance evidence or explicitly changed/waived in the approved BRS.
- Gates **G0–G8** are signed, no explicit no-go condition remains, and the external pilot completes its observation period without a data-boundary incident or unresolved release blocker.

## Complete Readiness-Gap Coverage

Each readiness gap has one primary delivery phase. Cross-phase dependencies remain listed in the phase checklists and acceptance gates.

| Readiness gap                                   | Primary phase | Required closure evidence                                                                                         |
| ----------------------------------------------- | ------------: | ----------------------------------------------------------------------------------------------------------------- |
| ER-01 — Cross-tenant relationship injection     |             1 | Two-tenant invariant matrix, integrity scan, constrained writes and runtime defense-in-depth                      |
| ER-02 — Unsafe SSO email linking/OIDC trust     |             1 | Standards-compliant OIDC negative suite, identity audit, no email auto-link or tenant-to-platform escalation      |
| ER-03 — Raw sensitive-data persistence/emission |             1 | Recursive sentinel suite across storage, queues, logs, webhooks, exports, browser props, errors, and retries      |
| ER-04 — Publication/runtime gate divergence     |             1 | One immutable-version-bound state machine across publish, approval, manifest, playground, evaluation, and runtime |
| ER-05 — Cross-tenant LLM credential reuse       |             1 | Sequential/concurrent tenant credential-isolation suite for queue/long-lived workers/failover/exceptions          |
| ER-06 — Broad authorization/data exposure       |             2 | Route/action/object/field/export/prop matrix and audited project-membership workflow                              |
| ER-07 — Incomplete SSRF/egress controls         |             2 | Unified outbound guard, egress policy, and DNS/redirect/IPv4/IPv6 SSRF regression suite                           |
| ER-08 — BRS/runtime/SDK contract drift          |             2 | Approved versioned contract, synchronized fixtures/SDKs/examples/migration guide, compatibility suite             |
| ER-09 — Non-atomic quotas/lifecycle             |             2 | Race, retry, crash, duplicate-delivery, expiry, hard-quota, and backpressure evidence                             |
| ER-10 — Misleading telemetry/reporting          |             3 | Currency/timestamp/health/usage reconciliation against independently auditable source facts                       |
| ER-11 — Unbounded console data loading          |             3 | Page-scoped pagination, query plans/indexes, and 10k/100k dataset p95 budgets                                     |
| ER-12 — Misrepresentative UI state/actions      |             3 | Browser mutation/error/recovery suite and no dead/false high-risk controls                                        |
| ER-13 — Responsive/accessibility failure        |             3 | WCAG 2.2 AA, keyboard, VoiceOver, zoom, theme, and 320–1440px evidence                                            |
| ER-14 — Insufficient release/resilience proof   |             4 | Immutable signed promotion, rollback, readiness, HA/load/restore/DR evidence, and sign-offs                       |
| ER-15 — Red aggregate CI                        |             1 | Clean-checkout local and hosted aggregate gates green and required                                                |

## Complete Edge-Case Coverage

| Primary phase | Edge cases closed                                                                                                                                        | Closure theme                                                                                                                                                                                     |
| ------------: | -------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
|             1 | EC-01, EC-11, EC-17, EC-18, EC-19                                                                                                                        | Tenant/provider isolation, owned polymorphic subjects, and safe SSO entitlement/discovery/throttling                                                                                              |
|             2 | EC-02, EC-03, EC-04, EC-05, EC-06, EC-07, EC-08, EC-09, EC-10, EC-12, EC-13, EC-14, EC-15, EC-16, EC-20, EC-21, EC-23, EC-24, EC-25, EC-26, EC-38, EC-39 | Validate before persistence, schema/output limits, idempotency/concurrency/jobs/quotas, safe errors, audit integrity, egress, ingestion, cleanup, caller trust, webhook semantics, and provenance |
|             3 | EC-22, EC-27, EC-28, EC-29, EC-30, EC-31, EC-32, EC-33, EC-34, EC-35, EC-36, EC-40                                                                       | Tenant-scoped caching, schema editor, charts, empty/error states, async UI races, secret handoff, approvals, environment/search truth, fonts, frontend maintainability, and coherent fixtures     |
|             4 | EC-37                                                                                                                                                    | Fail-closed production configuration and infrastructure defaults                                                                                                                                  |

## BRS and NFR Closure Map

All 56 functional requirements remain regression scope. The table below gives the primary remediation phase for every requirement rated Partial, Not Found/Deferred, or Implemented with a release-relevant bypass in the readiness review.

| Requirements                           | Primary phase | Closure result                                                                                           |
| -------------------------------------- | ------------: | -------------------------------------------------------------------------------------------------------- |
| FR-003, FR-004                         |             2 | Authoritative environment binding and complete audited project-member administration                     |
| FR-008, FR-009, FR-014, FR-015         |             1 | Owned/approved models, legal status transitions, immutable approval, and project allowlists              |
| FR-010                                 |             2 | Reproducible immutable agent versions covering every execution-relevant field                            |
| FR-011                                 |             3 | Governed draft testing and trustworthy Playground/browser state                                          |
| FR-016, FR-047                         |             3 | Reconciled currency, usage, project/agent/model/user/department reporting at scale                       |
| FR-018, FR-019                         |             1 | Enforced global/project tool ownership and assignment invariants                                         |
| FR-021, FR-023, FR-024, FR-025, FR-026 |             2 | Versioned schemas, correct implementation states, pre-persistence validation/limits, and atomic approval |
| FR-028                                 |             2 | Correct project/environment/global-tool manifest semantics                                               |
| FR-036, FR-040                         |             2 | Versioned status contract, proactive expiry, and exactly-once terminal behavior                          |
| FR-046                                 |             1 | Immediate retention/masking/no-store enforcement across every duplicate boundary                         |
| FR-049                                 |             2 | Authoritative tenant/platform capabilities, project membership, scoped props, and safe SSO roles         |
| FR-052                                 |             1 | Suspended/archived application denial at authentication, manifest, and runtime boundaries                |
| FR-053                                 |             2 | Signed minimized caller context in API, both SDKs, responses, and tool handlers                          |
| FR-054                                 |             1 | Unified tenant/environment/dependency/readiness enforcement end to end                                   |
| FR-056                                 |             2 | Authoritative schema, payload, egress, evaluation, approval, and safety-guardrail policy engine          |

| NFR category    | Primary phases | Required proof                                                                                                 |
| --------------- | -------------- | -------------------------------------------------------------------------------------------------------------- |
| Security        | 1, 2, 4        | Threat model, isolation/OIDC/data/key/egress/RBAC tests, independent review, production controls               |
| Availability    | 2, 4           | Idempotent lifecycle, SLOs, redundant topology, readiness, failover, and monitored recovery                    |
| Performance     | 3, 4           | Measurable page/runtime budgets, load profiles, target-volume datasets, and capacity report                    |
| Scalability     | 2, 3, 4        | Atomic quotas, backpressure, pagination/indexes, queue/storage/DB scaling, noisy-neighbor isolation            |
| Maintainability | 1–4            | ADR/traceability discipline, invariant/browser/load tests, typed frontend, clean deterministic gates           |
| Observability   | 2, 3, 4        | Least privilege, safe payloads, reconciled metrics, correlation, SIEM, dependency and SLO alerts               |
| Compliance      | 1, 2, 4        | Data inventory, no-store/masking, retention/legal hold, external keys, immutable audit, deletion/restore proof |
| Extensibility   | 2, 4           | Versioned contracts, bounded plugin/egress boundaries, compatibility and deprecation policy                    |
| Reliability     | 2, 4           | Atomic transitions, idempotency, outbox/expiry/job policies, chaos/failure and restore evidence                |
| Usability       | 3              | Authoritative workflows, recovery states, responsive/WCAG acceptance, and browser regression                   |

## Enterprise Gate Ownership

| Gate                         |                              Primary phase | Evidence owner                    |
| ---------------------------- | -----------------------------------------: | --------------------------------- |
| G0 — Baseline                |                                          1 | Product/Platform Owner + Security |
| G1 — Security                |               1–2, final confirmation in 4 | Security/IAM                      |
| G2 — BRS                     | 1 baseline, 2–3 implementation, 4 sign-off | Product Owner                     |
| G3 — Privacy/compliance      |                 1–2, production proof in 4 | Privacy/Compliance                |
| G4 — Product quality         |                                          3 | UX/Accessibility + QA             |
| G5 — Engineering             | 1 continuous gate, final confirmation in 4 | Engineering Lead                  |
| G6 — Performance/reliability |                 2–3, production proof in 4 | Architecture + SRE                |
| G7 — Operations              |                                          4 | SRE/Operations                    |
| G8 — Pilot                   |                                          4 | Go-live board                     |

## Cross-Phase Engineering Defaults

- Follow existing Laravel/Inertia/Wayfinder conventions and keep ownership, validation, authorization, and transition invariants in reusable domain services.
- Add focused Pest tests for every backend change and repeatable browser/accessibility/performance/security evidence for affected user or operational behavior.
- Test adversarial tenant, role, concurrency, retry, failure, stale-state, and large-data conditions rather than relying only on happy-path line coverage.
- Use versioned migrations and compatibility paths for public API/SDK/status/schema changes; update PHP SDK, TypeScript SDK, fixtures, reference apps, BRS, architecture, and user documentation together.
- Never persist secrets in plaintext or rely on UI hiding, UUID unpredictability, environment naming, or database foreign keys as authorization controls.
- Make production controls fail closed and observable; every background action must carry tenant, actor/application, correlation, policy/version, and idempotency provenance.
- Keep `composer ci:check`, full Pest coverage gate, PHPStan, Pint, ESLint, Prettier, TypeScript application/SDK checks, Node SDK tests, SDK fixture checks, build, security scans, and later browser/non-functional gates green.
- Keep the readiness review and this plan synchronized when evidence changes. Do not mark a checklist item complete without linking the implementation PR, automated test, operational artifact, approver, and residual-risk decision.

## Phase Completion Record

Use this table as the release ledger. A phase is complete only when its acceptance criteria and mapped gates are evidenced.

| Phase | Implementation PRs                                                                                      | Automated evidence                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                  | Operational evidence                                                                                                                                                                                                                                   | Approvals | Status                                      |
| ----- | ------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ | --------- | ------------------------------------------- |
| 1     | [PR #52 — Complete Phase 1 enterprise readiness hardening](https://github.com/hussain4real/AAC/pull/52) | [Phase 1 engineering evidence](enterprise-readiness-evidence/Phase_1_Engineering_Evidence_2026-07-15.md); [hosted PHP 8.4/8.5 enterprise gate](https://github.com/hussain4real/AAC/actions/runs/29438741125); [hosted linter](https://github.com/hussain4real/AAC/actions/runs/29438737742); [two-tenant matrix](enterprise-readiness-evidence/Phase_1_Two_Tenant_Adversarial_Matrix_2026-07-15.md); [threat/control register](enterprise-readiness-evidence/MAACC_Phase_1_Threat_Model_and_Control_Register_v1.md) | [Local tenant scan](enterprise-readiness-evidence/ER-01_Tenant_Integrity_Scan_2026-07-15.md); [local privileged SSO/session audit](enterprise-readiness-evidence/Phase_1_Privileged_SSO_Access_Audit_2026-07-15.md); real-environment evidence pending | _Pending_ | 🟨 Engineering verified; acceptance blocked |
| 2     | _Pending_                                                                                               | _Pending_                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | _Pending_                                                                                                                                                                                                                                              | _Pending_ | ⬜ Not started                              |
| 3     | _Pending_                                                                                               | _Pending_                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | _Pending_                                                                                                                                                                                                                                              | _Pending_ | ⬜ Not started                              |
| 4     | _Pending_                                                                                               | _Pending_                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                           | _Pending_                                                                                                                                                                                                                                              | _Pending_ | ⬜ Not started                              |

## Validation for This Document

- [x] Confirm this file exists at `docs/MAACC_Enterprise_Readiness_Remediation_Plan.md`.
- [x] Confirm the readiness review links to this plan using a valid relative Markdown link.
- [x] Confirm there are exactly four implementation phases and each includes `Goal`, `Checklist`, `Deliverables`, and `Acceptance Criteria` sections.
- [x] Confirm `ER-01` through `ER-15` each have one primary phase.
- [x] Confirm `EC-01` through `EC-40` each have one primary phase.
- [x] Confirm all Partial, Not Found/Deferred, and release-relevant bypassed BRS requirements are present in the closure map.
- [x] Confirm NFR categories and enterprise gates `G0` through `G8` are mapped.
- [x] Run Markdown formatting and `git diff --check`.
- [x] No application test suite is required for this documentation-only change.
