# MAAC Phased Implementation Plan

## Source Materials Reviewed

- `docs/MAAC_BRS(1).md`
- `docs/MAAC_Architecture_Document.md`
- `docs/MAAC-handoff.zip`
  - Primary prototype entrypoint: `maac/project/Milaha AI Agent Center.html`
  - Readable prototype source: `maac/project/MAAC.html` and `maac/project/maac/*.jsx`
  - Mock data model: `maac/project/maac/data.js`

## Overall Implementation Goal

Implement the Milaha AI Agent Center (MAAC) as an authenticated Laravel/Inertia platform that lets internal teams register applications, create projects and agents, define governed tool contracts, integrate application-owned tools through an SDK, invoke published agents through secure APIs, and audit every run from model call to tool result.

The implementation should preserve the central architectural decision from the BRS and architecture document: MAAC owns orchestration, agent configuration, tool contracts, governance, and auditability, while each registered application owns its local data access, permissions, and business logic for client-side tools.

## Phase 1: Prototype Alignment & Product Shell

> **Status: ✅ Complete** — branch `feature/maac-phase-1-console` (commit `92c93c3`). Built with shadcn-based primitives styled to match the prototype, team-scoped routing, and a front-end persona switcher. Verified: 93/93 Pest tests pass, ESLint/Prettier/`tsc`/`vite build`/Pint all clean, and every console screen confirmed rendering in the browser (light + dark) with no JS console errors. Demo login: `demo@milaha.com` / `password`.

### Goal

Convert the handoff prototype into a Laravel/Inertia MAAC console baseline that establishes the product navigation, visual language, role-aware information architecture, and mock-backed screen coverage.

### Checklist

- [x] Create an authenticated MAAC console area inside the current Inertia app.
- [x] Keep management pages within the existing Laravel authentication and team-aware shell until MAAC-specific roles are formalized.
- [x] Implement the MAAC sidebar/topbar structure from the prototype: Dashboard, Applications, Projects, Agents, Tools, SDK Implementation, Agent Playground, Runs & Audit Logs, LLM Providers, Governance, and Settings.
- [x] Translate Milaha design tokens from the prototype into the app's Tailwind/shadcn-style component system, including navy, purple, orange, teal, status tones, typography, compact cards, tables, badges, and dark-mode compatibility.
- [x] Build mock-backed dashboard metrics for runs, costs, token usage, LLM usage, tool implementation gaps, and governance alerts.
- [x] Build mock-backed list/detail pages for Applications, Projects, Agents, Tools, SDK Implementation Center, Playground, Runs, LLM Providers, Governance, and Settings.
- [x] Implement the Create Agent wizard screens for basic details, prompt, LLM selection, tools, runtime/safety, and review.
- [x] Implement the prototype's persona/scope behavior as an internal UX model or documented placeholder until final RBAC is available.
- [x] Use Wayfinder route helpers for frontend navigation and form/action wiring.
- [x] Add feature or browser smoke coverage for the authenticated MAAC pages and no-JavaScript-error checks on the main console routes.

### Deliverables

- Laravel/Inertia page skeletons for the full MAAC navigation map.
- Reusable MAAC UI primitives layered on existing app components.
- Mock data fixtures matching the prototype's applications, projects, tools, agents, runs, LLMs, policies, and personas.
- A clickable internal demo that mirrors the handoff prototype closely enough for stakeholder review.

### Acceptance Criteria

- Authenticated users can navigate every MAAC screen from the sidebar without missing routes.
- The console shows the core prototype workflows with mock data: application registration, agent creation, tool contract inspection, SDK implementation checklist, playground run simulation, and run trace review.
- The UI follows existing React/Inertia conventions and does not introduce a separate frontend framework.
- Focused Pest/browser smoke tests pass for the MAAC console routes.

## Phase 2: Core Platform Data Model

> **Status: ✅ Complete** — branch `feature/maac-phase-2-data-model`. The 13 core entities + RBAC concepts moved from the Phase 1 client fixture to UUID-keyed database records (each carries a `slug` route key seeded to the fixture id, so every URL and cross-reference is preserved). The console now reads real records through a shared `maac` Inertia prop backed by Eloquent API Resources, with secure (hashed, one-time-display) credentials and policy-authorized CRUD. Verified: 142 Pest tests pass; PHPStan level 7, Pint, ESLint, Prettier, `tsc`, and `vite build` all clean; browser walkthrough confirms every console screen renders from real data with no JS console errors. Governance/dashboard **display rollups** (roles, approvals, policies, sensitivity legend, dashboard aggregates) intentionally remain fixture-sourced pending Phase 5; the full per-role authorization **test matrix** is likewise deferred to Phase 5.

### Goal

Build the persistent management foundation for MAAC so applications, projects, credentials, models, agents, and tool contracts move from prototype data to governed database-backed records.

### Checklist

- [x] Create database models and migrations for Application, Project, Credential, LLM Provider, Agent, Agent Version, Tool Contract, Tool Assignment, Tool Implementation, Agent Run, Tool Call, Trace Event, and Audit Event.
- [x] Map the existing auth/team scaffold to the first MAAC ownership model without breaking current team routes.
- [x] Define role and permission concepts for Platform Admin, Project Owner, Developer, Viewer, Auditor, and Security Reviewer.
- [x] Implement application registration with environment-aware metadata: development, sandbox, staging, and production.
- [x] Implement secure application credential creation, hashed secret storage, one-time secret display, status, rotation metadata, and revocation state.
- [x] Implement project management under applications with member assignments, status, environment, business owner, and technical owner.
- [x] Implement the approved LLM catalog with provider, model code, context window, cost rates, sensitivity level, environment availability, and enabled/disabled status.
- [x] Implement agent drafts with slug, project, selected LLM, system prompt, runtime settings, status, version, and publication metadata.
- [x] Implement tool contracts with name, slug, description, scope, execution mode, input schema, output schema, sensitivity, approval requirement, timeout, payload limit, and version.
- [x] Implement tool assignments for global, project-level, and agent-level tools.
- [x] Add policies, form requests, factories, seeders, and feature tests for all core management resources.

### Deliverables

- Persistent relational schema for the MAAC platform.
- CRUD flows for applications, projects, LLM providers, agents, and tools.
- Initial seed data that represents the BRS/prototype examples.
- Authorization boundaries that can be expanded in later phases.

### Acceptance Criteria

- MAAC resources are stored in the database and rendered through Inertia props instead of static mock data.
- Application credentials can be generated, rotated, revoked, and displayed safely.
- Agents can be drafted and linked to projects, LLM providers, and tools.
- Tool contracts validate required metadata and JSON schema structure before save.
- Feature tests prove create, update, list, detail, authorization, and validation behavior for core resources.

## Phase 3: Tool Registry & SDK Integration MVP

> **Status: ✅ Complete** — branch `feature/maac-phase-3-sdk`. Tool contracts are now the governed source of truth, applications authenticate with Passport `client_credentials` tokens (each MAAC credential backs a Passport client; tokens issued at `/oauth/token`, 1‑hour lifetime, validated via `EnsureClientIsResourceOwner` + the `sdk.auth` middleware that resolves the credential → application). The SDK API exposes `GET /api/v1/manifest` (available agents + required client tools, schemas, contract versions, fingerprints, generated TS/PHP/Python stubs, and per‑environment implementation status) and `POST /api/v1/tool-implementations` (reports handlers; reconciles version + schema‑fingerprint compatibility into implemented/outdated/incompatible, with controlled per‑item errors for unknown/non‑client/disabled tools). Schema validation (`ToolSchema`) is enforced on contract writes and at runtime boundaries. The SDK Implementation Center renders real `ToolImplementation` records (status, last‑validated, last‑sync). Verified: 226 Pest tests pass at **100 % coverage**; PHPStan level 7, Pint, ESLint, Prettier, `tsc`, and `vite build` all clean. Full agent runtime (invoking these tools) remains Phase 4; `http`/`knowledge` execution modes exist as contract options only.

### Goal

Prove the core MAAC integration model: tool contracts are created first in MAAC, then application teams implement compatible local handlers through an SDK.

### Checklist

- [x] Build the Tool Registry as the source of truth for global, project-level, and agent-level tool contracts.
- [x] Support initial execution modes required for MVP: MAAC-hosted, client-side, remote HTTP, and knowledge retrieval as contract options, with only MAAC-hosted and client-side needing full runtime behavior in this phase.
- [x] Add JSON input schema and output schema validation for tool contracts.
- [x] Track client-side tool implementation status per application environment: not required, requires implementation, implemented, outdated, incompatible, disabled.
- [x] Add tool contract versioning with compatibility checks between current contract version and reported SDK implementation version.
- [x] Build SDK Implementation Center data from real contracts, assignments, application environments, and reported implementations.
- [x] Generate TypeScript and PHP SDK handler stubs from tool contracts.
- [x] Use Laravel Passport (`laravel/passport`) for SDK/API token issuance and validation, backed by project/application-scoped credentials and short-lived application access tokens.
- [x] Implement SDK manifest sync endpoints for applications to fetch required tools and report implemented handlers.
- [x] Add controlled errors for missing, outdated, incompatible, disabled, or unauthorized tool handlers.
- [x] Add tests for schema validation, implementation status transitions, credential auth, manifest sync, and stub generation.

### Deliverables

- Real Tool Registry and SDK Implementation Center.
- Authenticated SDK integration API for manifest sync and implementation reporting.
- Generated local handler stubs for client-side tools.
- Tool implementation compatibility status surfaced in application, tool, agent, dashboard, and SDK screens.

### Acceptance Criteria

- A developer can create a client-side tool contract in MAAC and see it appear as required for the owning application.
- An application can authenticate to MAAC, fetch required tool contracts, and report implemented tool versions.
- MAAC marks implementations as implemented, outdated, incompatible, or missing based on contract version and schema compatibility.
- Generated SDK stubs include the tool name, argument shape, output shape, permission placeholder, and result return pattern.
- Tests cover success and failure states for SDK sync and tool implementation reporting.

## Phase 4: Agent Runtime MVP

> **Status: ✅ Complete** — The agent run lifecycle is live behind an `LlmRouter` abstraction (`App\Support\Runtime`). MAAC owns the orchestration loop (so it can pause for client-side tools — the `laravel/ai` auto-executing agent loop cannot); the production `AiLlmRouter` drives `laravel/ai` one turn at a time via a compact JSON tool-call protocol, and tests bind a deterministic `FakeLlmRouter`. The runtime API (`POST /api/v1/agents/{agent_slug}/runs`, `GET /api/v1/runs/{run_id}`, `POST /api/v1/runs/{run_id}/tool-results`) is authenticated by the Phase 3 `sdk.auth` Passport client_credentials flow and authorized per-application by `RunAuthorizer`. `AgentRunner` drives queued→running→(hosted tool inline / pause for client tool)→completed, validating every payload against `ToolSchema` and recording a `TraceEvent` per milestone. MAAC-hosted tools execute in-platform via `HostedToolRegistry` (`echo`, `current_time` built-ins). Timeouts/expiry, payload-size limits, step (retry) limits, model-availability policy, and agent-unpublished cancellation all fail safely. Verified: 261 Pest tests at **100 % coverage**; PHPStan level 7, Pint, ESLint, Prettier, `tsc`, and `vite build` all clean. Remote HTTP/knowledge/connector tool execution remains Phase 6 (the runtime returns a controlled `unsupported_execution_mode` for them).

### Goal

Deliver the first real agent run lifecycle, including secure invocation, approved LLM calls, tool-call detection, client-side pause/resume, trace events, and final response payloads.

### Checklist

- [x] Implement `POST /api/v1/agents/{agent_slug}/runs` for authenticated application/SDK invocation.
- [x] Implement run status retrieval for applications and SDKs.
- [x] Implement `POST /api/v1/runs/{run_id}/tool-results` for client-side tool result submission.
- [x] Authenticate runtime API calls with Laravel Passport (`laravel/passport`) tokens issued to registered applications/SDK clients, then authorize each run against application credentials, project membership, agent publication status, environment, and model/tool policies.
- [x] Create Agent Run records with statuses: queued, running, requires_tool, waiting_for_client, completed, failed, expired, and cancelled.
- [x] Create Tool Call records whenever the runtime requests a tool.
- [x] Use Laravel AI SDK (`laravel/ai`) behind the LLM Router abstraction to call the first approved LLM provider.
- [x] Pass agent prompt, selected model, runtime settings, caller context, and assigned tool definitions into the runtime.
- [x] Detect model-requested tool calls and route them by execution mode.
- [x] Execute MAAC-hosted tools inside the platform for simple built-in utilities.
- [x] Pause runs for client-side tools and return a structured `requires_tool` response to the SDK.
- [x] Validate submitted tool results against output schema before resuming the run.
- [x] Resume the LLM call with validated tool results and compose a final response.
- [x] Enforce timeouts, payload size limits, retry limits, cancellation, and safe failure responses.
- [x] Record trace events for run requested, caller authenticated, model selected, prompt prepared, tool required, tool result received, validation, resume, completion, and failure.
- [x] Add tests for no-tool runs, client-side pause/resume runs, invalid tool results, expired runs, unauthorized invocations, and failed model/tool calls.

### Deliverables

- First working Agent Runtime API.
- LLM Router abstraction with one configured provider.
- Pause-and-resume flow for client-side tools.
- Run trace and tool call persistence.
- SDK-compatible response shapes for completed, waiting, failed, and expired runs.

### Acceptance Criteria

- A published agent can be invoked by a registered application through the runtime API.
- A run that does not need a client-side tool can complete and return response, usage, status, and run ID.
- A run that needs a client-side tool pauses, returns the tool request, accepts a valid result, resumes, and completes.
- Invalid, oversized, unauthorized, or late tool results fail safely and create audit/trace records.
- Runtime tests prove status transitions and trace events for the MVP lifecycle.

## Phase 5: Observability, Governance & Security Hardening

> **Status: ✅ Complete** — branch `feature/maac-phase-5-governance`. The dashboard and governance screens now render **real aggregates** (`RunMetrics`/`OperationalMonitor` → run status, hourly trend, top agents, token/cost totals, error/tool-failure rates, cost anomalies, and a severity-sorted alert feed) via the shared `maac` prop. A first-class **approval workflow** (`ApprovalRequest` + `ApprovalManager` + approve/reject actions) gates sensitive tool contracts, agent publication, model promotion, and production credential changes; approving applies the change (publish/activate/promote). **Masking** (`PayloadMasker`/`RunRedactor`) redacts Confidential run inputs and tool results at rest while keeping the live SDK/LLM paths on raw values; **retention** is configurable per environment and pruned by the scheduled `maac:prune-run-data` command. **Quotas** (`QuotaLimit`/`QuotaGuard`) enforce per-day run/token caps by platform/application/project/agent/model and environment at invocation (controlled `quota_exceeded` 429). Data **sensitivity** now classifies agents and runs (tools/models already did). A new **Audit Log** tab surfaces real `audit_events`; the full per-role **authorization matrix** (6 roles) and admin/runtime **audit tests** are covered. The approval queue UI is wired to the live approve/reject endpoints with a **360° Review modal** (per-type subject detail — tool schemas, agent prompt/model/assigned tools, model costs/availability, credential status/history). Approval decisions are **dependency-gated** by `ApprovalGate`: an agent publication cannot be approved while a required tool is still awaiting approval, is unimplemented in the target environment, or its model is not approved there — blockers are surfaced in the Review modal and the Approve control is disabled until they clear. Verified: 338 Pest tests at **100 % line coverage**; PHPStan level 7, Pint, ESLint, Prettier, `tsc`, and `vite build` all clean, with a live browser smoke of the dashboard and governance screens (no console errors). Remote HTTP/connector/knowledge tool execution and enterprise SSO/secrets-vault remain Phase 6.

### Goal

Make MAAC auditable, governable, and enterprise-safe for controlled production usage.

### Checklist

- [x] Build run and audit dashboards from Agent Run, Tool Call, Trace Event, usage, cost, and status data.
- [x] Track token input, token output, estimated cost, latency, model, provider, tool usage, failure reason, and caller context.
- [x] Implement configurable prompt, response, tool argument, and tool result retention policies.
- [x] Implement masking/redaction behavior for sensitive tool inputs and outputs.
- [x] Add data sensitivity classifications for tools, agents, runs, models, and logs.
- [x] Add approval queues for sensitive tool contracts, agent publication, model access, and production credential changes.
- [x] Add credential rotation, revocation, audit history, and last-used metadata.
- [x] Add rate limits and quotas by application, project, agent, model, and environment.
- [x] Add environment separation controls for credentials, agents, tool implementations, model availability, logs, and retention settings.
- [x] Add operational monitoring hooks for error rate, waiting runs, expired runs, average latency, cost anomalies, and tool failure rate.
- [x] Add authorization tests for Platform Admin, Project Owner, Developer, Viewer, Auditor, and Security Reviewer roles.
- [x] Add audit tests for key administrative and runtime events.
- [x] Wire the approval queue UI to the live approve/reject endpoints with a 360° Review modal (subject schemas, prompt, model, assigned tools, and history).
- [x] Gate approval decisions on unmet prerequisites — an agent cannot be published while a required tool awaits approval, is unimplemented in the target environment, or its model is not approved there (`ApprovalGate`, surfaced as blockers in the UI and enforced in `ApproveApprovalRequest`).

### Deliverables

- Production-oriented audit and reporting views.
- Governance workflows for agent/tool/model/credential changes.
- Security controls for retention, masking, revocation, rate limiting, and environment separation.
- Operational metrics suitable for monitoring and security review.

### Acceptance Criteria

- Security and audit users can trace who changed agents, tools, credentials, models, and policies.
- Every runtime call has enough trace detail to investigate status, latency, model usage, tool calls, and failure reason.
- Sensitive payload storage behavior follows configured retention and masking policies.
- Revoked credentials cannot authenticate, rotated credentials preserve history, and all credential events are audited.
- Governance and authorization test coverage proves role-specific access boundaries.

## Phase 6: Enterprise & Advanced Capabilities

> **Status: Planned, split for testability** — Phase 6 should not ship as one large enterprise bucket. Each sub-phase below must leave behind a repeatable end-to-end proof, external application integration evidence, and automated coverage that can fail independently. A Phase 6 capability is not complete until it is exercised through MAAC plus at least one SDK-consuming application where that capability affects the integration contract.

### Phase 6A: End-to-End Validation Harness

> **Status: ✅ Complete** — branch `feature/maac-phase-6a-e2e-harness`. A new `tests/Feature/E2E/` suite proves MAAC end-to-end from authenticated console setup to a completed, audited agent run. `ConsoleToRuntimeTest` drives the real Inertia console write endpoints (register application → add approved model → create project → create client-side tool contract → create agent with the tool assigned → publish), captures the one-time credential secret from the `credentialSecret` flash, exchanges it at the real `POST /oauth/token`, fetches the SDK manifest (tool **`required`**), reports a compatible handler, re-fetches the manifest to confirm the **`required` → `implemented`** transition, invokes the agent (pauses `waiting_for_client`), submits the tool result, completes the run, reads run status, and asserts trace events + token/cost + audit events. `SdkRuntimeContractTest` pins the response **shape** of every surface (`/oauth/token`, `GET /api/v1/manifest`, `POST /api/v1/tool-implementations`, `POST /api/v1/agents/{agent_slug}/runs`, `GET /api/v1/runs/{run_id}`, `POST /api/v1/runs/{run_id}/tool-results`). `FailurePathsTest` covers the controlled failure matrix (revoked credential, wrong environment, unpublished agent, missing hosted handler, incompatible schema, oversized tool result, expired run). A **deterministic fake-provider mode** (`MAAC_LLM_DRIVER=fake` → `App\Support\Runtime\DeterministicLlmRouter`, bound in `RuntimeServiceProvider`) drives the whole lifecycle with no model spend or network dependency by synthesizing schema-valid tool arguments from the contract; `FakeProviderModeTest` proves both binding arms and a full pause/resume/complete run through the real container binding. A deterministic, idempotent `Database\Seeders\MaacE2ESeeder` (stable public slug constants, Passport-backed credential) is the canonical fixture for both the suite and a served smoke. Console setup-path **browser coverage** is delivered as Inertia page-render assertions (the repo carries no browser-test infrastructure; this matches every prior phase) plus a manual Chrome walkthrough. **Commands:** `composer test:e2e` (focused gate, `php artisan test tests/Feature/E2E`), plus the standard `composer ci:check` and `php artisan test --coverage --min=100`; the served smoke is `php artisan migrate:fresh --seed && php artisan db:seed --class=MaacE2ESeeder` with `MAAC_LLM_DRIVER=fake`. Verified: 362 Pest tests at **100 % line coverage**; PHPStan level 7, Pint, ESLint, Prettier, `tsc`, and `vite build` all clean.

#### Goal

Create a repeatable, automated validation path that proves MAAC works from management-console setup through SDK-authenticated agent invocation, client-side tool execution, final run completion, and audit review.

#### Checklist

- [x] Define the canonical MAAC E2E scenario matrix: register application, create project, create LLM provider, create agent, create client-side tool contract, generate credential, fetch SDK manifest, report tool implementation, invoke published agent, pause for tool, submit tool result, complete run, and verify trace/audit/cost data.
- [x] Add deterministic seed fixtures for the E2E matrix so local and CI runs use stable teams, applications, credentials, agents, tools, and model responses.
- [x] Add browser coverage for the authenticated Inertia console setup path: application registration, credential generation/rotation, tool contract creation, agent publication, governance approval, and run/audit inspection.
- [x] Add API contract coverage for the runtime and SDK surfaces: `/oauth/token`, `GET /api/v1/manifest`, `POST /api/v1/tool-implementations`, `POST /api/v1/agents/{agent_slug}/runs`, run status retrieval, and `POST /api/v1/runs/{run_id}/tool-results`.
- [x] Add a fake or deterministic LLM provider mode for E2E tests so the workflow can run without external model spend or flaky network dependency.
- [x] Add controlled failure-path E2E coverage for revoked credentials, wrong environment, unpublished agent, missing tool handler, incompatible schema, oversized tool result, and expired run.
- [x] Document the local and CI commands required to run the full MAAC E2E gate.

#### Deliverables

- A runnable MAAC E2E validation suite.
- Deterministic E2E seed data and fake-provider configuration.
- A documented smoke command for developers and CI.
- Contract-level assertions for the SDK/runtime API response shapes.

#### Acceptance Criteria

- A developer can run one documented command and prove the complete MAAC happy path from console setup to completed agent run.
- The E2E suite verifies that the SDK manifest changes from "requires implementation" to "implemented" after the external app reports a compatible handler.
- The suite proves runtime pause/resume with a client-side tool and confirms trace events, audit events, token/cost metadata, and final response payload.
- `composer ci:check`, `php artisan test --coverage --min=100 --compact`, `npm run build`, and the new E2E smoke gate pass before Phase 6A is marked complete.

### Phase 6B: External SDK Reference Applications

> **Status: ✅ Complete** — branch `feature/maac-phase-6b-reference-apps`. Two reusable SDK client packages were extracted — a **framework-agnostic PHP SDK** (`packages/maac-sdk-php`, namespace `Maac\Sdk\`, only ext-curl/ext-json; default `CurlTransport`, swappable `Transport`) and a **dependency-free TypeScript SDK** (`packages/maac-sdk-ts`, `@maac/sdk`, global `fetch`). Both implement the full contract: client_credentials token exchange (cached + refreshed, 401-retry), manifest sync, `reportHandlers()` reconciliation, `startRun`/`getRun`/`submitToolResult`, and a one-call auto-resume `run()` loop that services client-side tool pauses from a local `ToolHandlerRegistry` (typed errors: `MaacApiException`/`MaacApiError`, `MissingToolHandler*`, `RunNotResolved*`). **Three reference consumers** ride on them: a **Laravel consumer** (`reference-apps/laravel-consumer` — service provider + `maac:run-agent` command + app-owned `CargoRepository` handler), a **plain-PHP CLI consumer** (`reference-apps/php-cli-consumer`, no Illuminate), and a **Node/TypeScript consumer** (`reference-apps/node-consumer`) — the latter proving the contract is not Laravel- or PHP-only. **Integration tests** run the SDK + consumers against the seeded canonical scenario (`MaacE2ESeeder`) through an in-process kernel transport (`tests/Support/Sdk/KernelTransport` → MAAC's real HTTP stack, real OAuth token, no network): token exchange, manifest sync, the **required → implemented** transition, run invocation, client-side pause/resume, and final-status read. A **negative matrix** covers revoked credentials, outdated/incompatible reports, unknown-tool reports, cross-tenant (`agent_not_found`) and unpublished agent access, oversized tool result, and a missing local handler. SDK internals are unit-tested with scripted fakes (`FakeTransport`, Node mock transport). Onboarding docs + a compatibility matrix live in `docs/MAAC_SDK_Integration_Guide.md` and per-package READMEs. **Commands:** `composer test:reference` (PHP), `npm run test:sdk` + `npm run types:check:sdk` (Node — wired into `composer ci:check`). Verified: 405 Pest tests at **100 % line coverage** (`app/`), 10 Node tests, PHPStan level 7 (now including the SDK + reference `src/`), Pint, ESLint, Prettier, and both frontend + SDK `tsc` all clean, plus a live Chrome walkthrough confirming external-consumer runs surface on the dashboard, SDK Implementation Center, run trace, and audit log.

#### Goal

Prove MAAC can be integrated by real applications outside the MAAC codebase, starting with a Laravel reference consumer and one second priority stack.

#### Checklist

- [x] Create a Laravel reference consumer application that obtains a Passport `client_credentials` token, fetches the MAAC manifest, registers local tool handlers, reports implementation status, invokes an agent, handles `requires_tool`, submits tool results, and reads final run status.
- [x] Create a second reference consumer in another priority stack, such as TypeScript/Node or a lightweight PHP CLI app, using the same manifest and runtime contracts. *(Both delivered: Node/TypeScript and a plain-PHP CLI.)*
- [x] Package reusable SDK client code rather than copying request logic into each reference app. *(`milaha/maac-sdk` for PHP, `@maac/sdk` for TypeScript.)*
- [x] Add consumer-app integration tests that run against a seeded MAAC test instance and assert token exchange, manifest sync, implementation reporting, run invocation, pause/resume, and final response handling.
- [x] Add negative integration tests for revoked credentials, stale implementation versions, schema fingerprint mismatch, unauthorized agent access, and missing local handlers.
- [x] Document required environment variables, token exchange flow, handler registration pattern, and troubleshooting steps for external application teams. *(`docs/MAAC_SDK_Integration_Guide.md` + per-package READMEs.)*
- [x] Add a compatibility matrix that names which SDK languages and application stacks are supported, experimental, or planned.

#### Deliverables

- At least two SDK-consuming reference applications.
- Reusable SDK client package or packages.
- Cross-application integration tests wired into the MAAC validation workflow.
- Developer documentation for onboarding another application to MAAC.

#### Acceptance Criteria

- The Laravel reference consumer can complete an agent run with a client-side tool using only public MAAC SDK/runtime APIs.
- The second reference consumer proves the integration contract is not Laravel-only.
- MAAC dashboard, SDK Implementation Center, run trace, and audit log reflect activity from the external consumer apps.
- A fresh developer can follow the documentation and connect a new application without inspecting MAAC internals.

### Phase 6C: SDK Distribution, Versioning & Compatibility

> **Status: ✅ Complete** — branch `feature/maac-phase-6c-sdk-versioning`. The Phase 3 SDK surfaces are now a **versioned integration product**. A new `config('maac.sdk')` block + `App\Support\Sdk\SdkPlatform` define the API **contract version**, the supported **client-package window**, the published-package registry, and deprecations; a new authenticated **`GET /api/v1/sdk`** (`SdkVersionController`) negotiates the caller's reported SDK version (`X-Maac-Sdk-Version`/`-Language` header or `client_version` query) into a `compatible` / `upgrade_required` / `ahead` / `unknown` verdict, every v1 response carries an **`X-Maac-Api-Version`** header (`AddApiVersionHeader`), and the manifest embeds the same `sdk` block. Both SDKs were **versioned to 0.0.1** (`MaacClient::VERSION` / `SDK_VERSION`), now send their version on every request + in reports (new `tool_implementations.sdk_version` column, captured by `ReportToolImplementation`), and gained `compatibility()`. **SDK test helpers** (`Maac\Sdk\Testing\SchemaValidator`/`ToolTester`/`Compatibility`; TS `validateSchema`/`ToolTester`/`evaluateCompatibility`) let an app validate handlers against the contract schema before reporting. A **shared contract fixture suite** (`packages/sdk-fixtures/contract.json`) is generated from MAAC's own logic by `App\Support\Sdk\ContractFixtures` via `php artisan maac:sdk-fixtures` (`--check` is the CI tripwire, wired into `composer ci:check`) and is run by MAAC, the PHP SDK, and the TS SDK — so a response-shape/rule change can't silently break a client. A **compatibility dashboard** (`SdkCompatibilityReport` → `maac.sdkCompatibility` → SDK Implementation Center) shows platform versions, per-application reported-client compatibility, and the **contract drift feed**. Versioned **changelogs**, a **Migration Guide** (`docs/MAAC_SDK_Migration_Guide.md`), per-package READMEs, and simple/advanced **examples** (incl. controlled missing-handler) round it out. Verified: **466 Pest tests at 100 % line coverage**; PHPStan L7, Pint, ESLint, Prettier, `tsc` (frontend + SDK + examples), 19 Node tests, and `npm run build` all clean — plus a live Chrome walkthrough of the compatibility dashboard (real reported clients: a compatible PHP v0.0.1, an upgrade-required TS v0.0.0, and a drifted tool) and a served-API smoke of `GET /api/v1/sdk` (negotiation + `X-Maac-Api-Version` header) with no console errors.

#### Goal

Turn the Phase 3 SDK surfaces into a versioned integration product that can be safely adopted, upgraded, and tested by application teams.

#### Checklist

- [x] Define SDK package boundaries for generated stubs, runtime client, manifest sync, credential/token management, local handler registry, and test helpers.
- [x] Add semantic versioning for SDK packages and generated contract artifacts.
- [x] Add migration guides, deprecation windows, changelog entries, and compatibility dashboards for tool contract version changes.
- [x] Add SDK test helpers so external applications can validate local handlers against MAAC tool input/output schemas before reporting them as implemented.
- [x] Add a contract fixture suite that every supported SDK language must pass.
- [x] Add CI checks that prevent a MAAC API response-shape change from silently breaking supported SDK clients.
- [x] Add SDK examples for simple mode and advanced mode, including controlled missing-handler behavior.

#### Deliverables

- Versioned SDK packages and generated artifacts.
- Compatibility dashboard and migration/deprecation workflow.
- Shared SDK contract fixture suite.
- Example integrations for supported SDK modes.

#### Acceptance Criteria

- SDK consumers can detect whether their package version and tool implementation versions are compatible with MAAC.
- Contract changes are visible before deployment and include a documented migration path.
- Supported SDK languages pass the same manifest, tool handler, run invocation, and failure-mode fixture suite.

### Phase 6D: Async, Streaming, Polling & Webhook Runtime Modes

> **Status: ✅ Complete** — branch `feature/maac-phase-6d-async-runtime`. The runtime now supports long-running and interactive runs alongside the synchronous path. A new `agent_runs.mode` (`sync`/`async`) records how a run was invoked: `AgentRunner` was refactored into `createRun`/`process`/`acceptToolResult`/`drive` (the synchronous `start()`/`resume()` are preserved), and `POST /api/v1/agents/{slug}/runs` accepts `mode: async` → creates the run, returns **`202 queued`**, and a worker (`ProcessAgentRun`) drives it; a client-side tool result on an async run is accepted (`202`) and continued by `AdvanceAgentRun`. **Polling** is the existing `GET /api/v1/runs/{id}` plus SDK `pollRun()`/`runAsync()`. **Streaming** is a new SSE endpoint `GET /api/v1/runs/{id}/stream` (`RunStreamController` via `response()->eventStream()`) that replays the run's trace events to a boundary — so a streamed run produces the same trace/audit/cost data as a sync run. **Webhooks**: applications self-register endpoints (`POST/GET/DELETE /api/v1/webhook-endpoints`, one-time signing secret) or manage them on a new console **Webhooks** page; `RunWebhookEmitter` (wired into every run transition) fans `run.running`/`run.tool_requested`/`run.completed`/`run.failed`/`run.expired`/`run.cancelled` events to subscribed endpoints, and `DeliverWebhook` posts an **HMAC-SHA256-signed** payload with its own retry/backoff, persisting every attempt (the `webhook_deliveries` audit trail) — failures are observable and **replayable** from the console. Both SDKs gained `startRun(mode)`, `pollRun`, `runAsync`, `registerWebhook`/`listWebhooks`/`deleteWebhook`, `streamRun`, and a `WebhookSignature`/`verifyWebhook` helper (pinned by a new `webhook_signature` shared contract fixture), and were bumped to **0.1.0** (the API contract stays v0.0.1, additively advertising a `capabilities` block). Reference consumers gained async/polling + a signature-verifying webhook receiver, exercised end-to-end through the in-process `KernelTransport` (now buffers streamed responses). Verified: **509 Pest tests at 100 % line coverage**; PHPStan L7, Pint, ESLint, Prettier, `tsc` (frontend + SDK), 30 Node tests, `maac:sdk-fixtures --check`, and `npm run build` all clean — plus a live Chrome walkthrough of the Webhooks console page and SDK docs.

#### Goal

Support long-running and interactive agent experiences without weakening auditability, timeout controls, authorization, or SDK ergonomics.

#### Checklist

- [x] Add asynchronous runtime mode for long-running agent runs.
- [x] Add polling SDK integration mode for applications that cannot hold open a request.
- [x] Add webhook delivery for run status changes, tool requests, completion, failure, and expiry.
- [x] Add streaming runtime events for chat-style or progress-oriented interfaces.
- [x] Persist delivery attempts, replay state, webhook signatures, and failure reasons.
- [x] Add SDK support for polling, webhooks, and streaming with resumable error handling.
- [x] Add E2E tests from external reference apps for async, polling, webhook, and streaming paths.
- [x] Update the SDK docs (`resources/js/pages/maac/sdk-docs.tsx` + `docs/MAAC_SDK_Integration_Guide.md`): move async / webhook / streaming from "Coming soon" to Supported in the compatibility matrix and document its usage + examples.

#### Deliverables

- Async run API and worker-backed lifecycle.
- Polling, webhook, and streaming SDK modes.
- Webhook signature validation and delivery audit trail.
- External app tests for long-running and interactive workflows.

#### Acceptance Criteria

- A reference app can start a long-running run, receive progress through polling or webhooks, execute a client-side tool, and receive final completion.
- Streaming and async paths produce the same core trace, audit, quota, cost, and retention data as synchronous runs.
- Webhook failures are observable, retryable, and safely signed.

### Phase 6E: Remote HTTP Tools & Laravel MCP Connectors

> **Status: ✅ Complete** — branch `feature/maac-phase-6e-remote-mcp-tools`. The two server-side execution modes that previously dead-ended at `unsupported_execution_mode` are now real, behind dedicated executors the `AgentRunner` routes to (alongside hosted/client). **Remote HTTP tools** (`App\Support\Runtime\Remote\RemoteHttpToolExecutor`) enforce an egress allowlist (`config('maac.runtime.remote_http')`, `*.`-wildcards + a loopback/link-local/metadata denylist as SSRF defense), a method constraint (`HttpMethod`), auth (`RemoteAuthType` none/bearer/header — credential stored `encrypted:array` in `tool_contracts.http_config`, never returned), and a retry/timeout policy, returning a JSON object validated against the output schema. **MCP connector tools** use the **real `laravel/mcp` client** (`Client::web(...)->callTool(...)`) via `McpConnectorClientFactory` + `McpToolExecutor`; a new `mcp_connectors` table (registration, encrypted auth, env availability, cached `capabilities`) is discovered by `McpCapabilityDiscoverer` and mapped to a contract by `mcp_connector_id` + `mcp_tool_name`. Every failure is a controlled run failure (`remote_http_blocked`/`_unreachable`/`_unauthorized`/`_failed`/`_invalid_output`, `connector_misconfigured`/`_unavailable`/`_unreachable`/`_unauthorized`/`_failed`/`_invalid_output`) with a trace event; results are field-redactable at rest (`tool_contracts.redaction`) while the LLM still sees raw values. Server-side egress tools that require approval start `Draft` and a runtime guard blocks them until activated; `ApprovalGate` blocks agent publication while an assigned connector tool's connector is disabled/unavailable, and the approval review surfaces endpoint/method/auth/redaction for egress review. The SDK manifest now distinguishes tool types — each agent carries `server_tools` (hosted/http/connector, tagged with mode) alongside the client `tools`, and `sdk.capabilities.tool_execution_modes` advertises client- vs MAAC-executed modes; both SDKs expose `ManifestAgent.serverTools` and were bumped to **0.2.0** (API contract stays v0.0.1). A new **MCP Connectors** console page registers/discovers/manages connectors, and the tool form gained conditional HTTP/connector config sections. Verified: **579 Pest tests at 100 % line coverage**; PHPStan L7, Pint, ESLint, Prettier, `tsc` (frontend + SDK), 30 Node tests, `maac:sdk-fixtures --check`, and `npm run build` all green — plus a live Chrome walkthrough (register a connector, the controlled discovery-failure toast, the HTTP/connector tool-form config, and the SDK docs Server-side-tools section + matrix) with no console errors. Knowledge-retrieval and read-only-DB remain the only `unsupported_execution_mode`s (deferred to Phase 6F).

#### Goal

Expand beyond client-side and MAAC-hosted tools while preserving the same tool-contract-first model, schema validation, policy enforcement, and observability.

#### Checklist

- [x] Implement remote HTTP tools with allowlisted endpoints, method constraints, auth configuration, retry policy, timeout policy, response validation, and redaction rules.
- [x] Add approval gates for production remote HTTP tools, including endpoint, auth, sensitivity, and egress review.
- [x] Use Laravel MCP (`laravel/mcp`) to implement connector server support where MCP tools, resources, or prompts fit the connector contract.
- [x] Add MCP connector registration, capability discovery, permission mapping, and trace/audit recording.
- [x] Add reference connector integration tests that execute an MCP-backed tool from an external application context.
- [x] Add controlled failures for unreachable endpoints, blocked domains, invalid connector output, unauthorized connector access, and connector timeout.
- [x] Update SDK manifests so external apps can distinguish client-side tools from MAAC-hosted, remote HTTP, and MCP-backed tools.
- [x] Update the SDK docs (`resources/js/pages/maac/sdk-docs.tsx` + `docs/MAAC_SDK_Integration_Guide.md`): move remote HTTP & MCP tools from "Coming soon" to Supported in the compatibility matrix and document them + examples.

#### Deliverables

- Remote HTTP execution engine.
- Laravel MCP connector support.
- Governance workflow for remote and connector tools.
- E2E tests proving remote and MCP-backed tools through the runtime.

#### Acceptance Criteria

- Remote HTTP and MCP tools follow the same schema, versioning, trace, audit, quota, sensitivity, and retention standards as existing tools.
- External reference apps can invoke agents that use remote or MCP-backed tools without direct database access to the consuming application.
- Unsupported execution modes are replaced by tested implementations or explicit, documented non-goals.

### Phase 6F: Knowledge Retrieval/RAG & Evaluation Lab

> **Status: ✅ Complete** — branch `feature/maac-phase-6f-rag-evaluation`. The `knowledge` execution mode that previously dead-ended at `unsupported_execution_mode` is now a real, governed RAG capability, and a first-class **Evaluation Lab** tests agent quality/safety/citations/regressions and gates promotion. **Knowledge sources**: a `knowledge_sources`/`knowledge_documents`/`knowledge_chunks` model with a `KnowledgeIndexer` (paragraph→word-window chunking + tokenizer) and a pluggable `KnowledgeRetriever` (default deterministic `LexicalKnowledgeRetriever` — query-term coverage scoring, no embedding spend, bound in `RuntimeServiceProvider` so an embedding retriever can swap in). `KnowledgeToolExecutor` (routed from `AgentRunner::handleToolCall`, alongside hosted/http/connector) enforces source active+environment availability, returns schema-validated `{matches, citations}` (citation = document/uri/chunk/score/freshness), and fails with controlled `knowledge_misconfigured`/`_unavailable`/`_failed`/`_invalid_output` codes. A sensitive (Confidential+) or flagged source starts `Draft` and opens a **`ApprovalType::KnowledgeIngestion`** approval (approving activates it); the runtime won't retrieve from a non-active source. **Evaluation Lab**: `evaluation_datasets`/`evaluation_cases` (no-tool/client-tool/remote-tool/connector/RAG kinds + per-case assertions + client-tool stubs) → `EvaluationRunner` drives each case through the **real** `AgentRunner` (servicing client-tool pauses from stubs; `agent_runs.evaluation_id` lets an evaluation run a not-yet-published candidate) → `EvaluationGrader` records per-check verdicts (completion/correctness/tool/citation/safety/cost/latency) → rolls up pass/correctness/safety/citation rates + cost/latency onto the `evaluations` record. The **promotion gate** (`EvaluationGate`, wired into both `ApprovalGate::agentBlockers` and the direct `AgentController::publish`) blocks publishing while the latest required evaluation per dataset has not passed. Comparison across agent versions (version/prompt-fingerprint/model/cost/latency/correctness/safety/citation deltas) is rendered client-side from the evaluation metrics. New console pages **Knowledge Sources** (Integrate) and **Evaluation Lab** (Validate); the tool form gained a knowledge-config section; the SDK manifest advertises `knowledge` under `capabilities.tool_execution_modes.server_side` and `server_tools`, and the SDK docs/integration-guide move RAG to Supported. Verified: **650 Pest tests at 100 % line coverage**; PHPStan L7, Pint, ESLint, Prettier, `tsc` (frontend + SDK), 30 Node tests, `maac:sdk-fixtures --check`, and `npm run build` all green via `composer ci:check` — plus a live Chrome walkthrough (knowledge source + indexed documents with citations/freshness, a live source registration, the evaluation runs with per-case checks + inspected citation, the cross-version comparison, and the required promotion gate) with no console errors. Knowledge-retrieval is now implemented; **read-only DB (`db`) remains the only documented `unsupported_execution_mode`**, deferred to a later phase.

#### Goal

Add governed knowledge retrieval and evaluation workflows so teams can test agent quality, safety, regressions, and source attribution before production rollout.

#### Checklist

- [x] Implement knowledge retrieval/RAG tools with approved document sources, indexing pipeline, citation metadata, freshness metadata, and access controls.
- [x] Add ingestion approvals for sensitive document sources and environment-specific indexes.
- [x] Add evaluation lab capabilities for prompt, tool, model, regression, citation, and safety testing.
- [x] Add golden test datasets that exercise no-tool, client-tool, remote-tool, connector, and RAG workflows.
- [x] Add comparison reports for agent version, prompt version, model, tool contract version, cost, latency, correctness, and safety outcomes.
- [x] Add promotion gates that prevent publishing a risky agent version when required evaluations fail.
- [x] Add E2E tests that run an evaluation against seeded data and verify dashboard/audit visibility.

#### Deliverables

- Governed RAG tool capability.
- Evaluation lab UI and backend workflow.
- Golden datasets and evaluation reports.
- Promotion gates tied to evaluation results.

#### Acceptance Criteria

- A project owner can index an approved source, assign a RAG tool to an agent, run an evaluation, inspect citations, and compare behavior across versions.
- Evaluation outcomes can block or approve production promotion according to governance policy.
- RAG and evaluation activity is auditable and visible in run traces, dashboards, and approval history.

### Phase 6G: Enterprise Identity, Secrets & Advanced Governance

> **Status: ✅ Complete** — branch `feature/maac-phase-6g-enterprise-identity`. The six enterprise-hardening surfaces are live, each opt-in so the prior 650 tests stay green. **Enterprise SSO/IAM**: a real OAuth 2.0 / OIDC authorization-code flow over the HTTP client (`App\Support\Sso\SsoAuthenticator` → token + userinfo, fully `Http::fake`-able — no new dependency), `App\Support\Sso\SsoUserResolver` recognizes/provisions a user and maps the IdP group claim onto the connection's MAAC team role + project `MaacRole`s (the connection is authoritative for its users), every login writes a `sso.login`/`sso.provisioned` audit event, `sso_connections` + `sso_identities` tables, guest `GET /sso/{connection}/redirect|callback` routes, an admin **Enterprise Identity** console page, and "Continue with …" buttons on the login screen (local password auth preserved). **Secrets vault**: a `SecretVault` contract + default `DatabaseSecretVault` (encrypted at rest, versioned rotation, access tracking) bound config-driven so an external vault can swap in; `llm_providers.vault_secret_id` lets the runtime resolve a provider's API key from the vault at call time (`AgentRunner::buildRequest` → `LlmRequest::apiKey` → `AiLlmRouter` config override), so a central rotation takes effect on the next run — proven E2E. **Advanced model routing**: a per-agent `ModelRoutingPolicy` (strategy cost/latency/balanced, ordered primary+fallback chain, cost/latency ceilings) applied by `App\Support\Runtime\Routing\ModelRouter` in `AgentRunner::process` — filters candidates by environment availability, sensitivity clearance, cost ceiling, and recent `ProviderHealth` (model-attributable failure rate + latency from the run history), records the decision on the run trace, and **fails over** along the chain when a model call errors mid-run. **Human-in-the-loop runtime approval**: a per-agent `requires_runtime_approval` flag (or a team `runtime_approval_sensitivity` threshold) pauses a run at `requires_approval` via the existing approval system (new `ApprovalType::RuntimeAction`, subject = the run); approving resumes it (worker-driven), rejecting fails it `approval_denied`. **Break-glass / incident response**: `BreakGlassManager` performs one-click audited containment — revoke a credential, disable a model, shut down a connector, suspend a webhook, or freeze/lift an application's runtime — each recording an `IncidentAction` (the immutable timeline) + a high-severity audit event; an `IncidentGuard` rejects runs against a frozen application (controlled `runtime_frozen` 423) and the runtime halts in-flight frozen runs. **Enterprise audit export**: `AuditExporter` + `GET /audit-export` streams a filtered, SHA-256-signed (manifest + `X-Maac-Audit-Checksum`) JSON/CSV slice of the audit log for security review, on top of the Phase 5 audit retention controls. New console pages (Secrets Vault, Model Routing, Enterprise Identity, Incident Response) ride the shared `maac` prop via `EnterpriseConsoleData`. Verified: **776 Pest tests at 100 % line coverage** (incl. a `Phase6GEnterpriseTest` E2E chaining SSO role mapping → vault rotation → routing fail-over → runtime approval → break-glass freeze → signed audit export), PHPStan L7, Pint, ESLint, Prettier, `tsc` (frontend + SDK), 30 Node tests, `maac:sdk-fixtures --check`, and `npm run build` all green via `composer ci:check` — plus a live Chrome walkthrough of all four new pages + the login SSO button (which caught and fixed a Radix empty-`value` Select crash on the incidents page) and an end-to-end "Store secret" write (toast + DB persistence) with no console errors. The SDK contract is unchanged (this phase hardens the console/runtime, not the SDK surface); **read-only DB (`db`) remains the only documented `unsupported_execution_mode`**.

#### Goal

Harden MAAC for enterprise operation once the integration and runtime surfaces have repeatable E2E proof.

#### Checklist

- [x] Integrate enterprise SSO/IAM for web users, mapped to MAAC roles and project/application membership.
- [x] Integrate a secrets vault for LLM provider keys, application credential material, remote tool secrets, webhook secrets, and connector credentials.
- [x] Add advanced model routing policies for sensitivity, cost, latency, fallback, provider health, and environment.
- [x] Add human-in-the-loop approval steps for sensitive runtime actions.
- [x] Add enterprise audit export and retention controls for security review.
- [x] Add break-glass and incident-response controls for credential revocation, model disablement, connector shutdown, and webhook suspension.
- [x] Add E2E regression coverage proving SSO role mapping, vault-backed secret rotation, advanced routing, and human approvals.

#### Deliverables

- Enterprise identity and role mapping.
- Vault-backed secret storage and rotation.
- Advanced routing and human approval policies.
- Enterprise audit export and incident-response controls.

#### Acceptance Criteria

- Enterprise authentication and secret storage replace local placeholders without changing MAAC's product model.
- Role mapping, vault rotation, model routing, and sensitive runtime approvals are verified by automated E2E tests.
- Security reviewers can trace user identity, application identity, secret usage, model routing decisions, and approval decisions across management and runtime events.

## Phase 7: Management Console Interactivity (UI ↔ API Wiring)

> **Status: ✅ Complete** — branch `feature/maac-phase-7-console-wiring`. Across Phases 2–5 the console shipped fully tested backend write endpoints, but the React/Inertia screens still rendered their action buttons and forms as demo shells (only the Phase 5 governance Approve/Reject/Review controls were wired). Phase 7 connects the rest end-to-end so the console is genuinely usable — no new business logic, just wiring the existing, feature-tested APIs to the UI (plus the small amount of form/modal UI and the few missing controls needed to drive them). Every create/edit/delete modal uses Inertia's `useForm` (or `router` for one-shot actions) against the existing Wayfinder actions, surfaces server-side validation errors inline, refreshes the shared `maac` prop on success (controllers `Inertia::flash('toast', …)->back()`), and renders success toasts through the existing Sonner flash listener. A new `CredentialSecretGate` (mounted once in the console layout) surfaces the one-time `credentialSecret` flash on generate/rotate. The API resources gained an additive `uuid` (so forms can submit the related-record ids the FormRequests validate) and applications now expose their safe `credentials` records (so the credentials tab can drive rotate/revoke). Verified: 339 Pest tests at **100 % line coverage**; PHPStan level 7, Pint, ESLint, Prettier, `tsc`, and `vite build` all clean. Live browser walkthrough (Chrome, Platform Admin persona) confirmed end-to-end with no console errors: register application + edit; generate / rotate credential with the one-time secret modal; governance settings save (persisted toggles); quota create; request approval; and tool-contract create via the schema editor.

### Goal

Make every MAAC console action button perform its real, governed action end-to-end against the already-tested write endpoints, eliminating the remaining demo-shell controls.

### Checklist

- [x] Applications — wire "Register Application" (`applications.store`), edit / archive (`applications.update` / `applications.destroy`), and suspend/activate status changes.
- [x] Applications credentials — wire "Generate Secret" (`applications.credentials.store`, one-time secret via the `credentialSecret` flash) and "Revoke access" (`credentials.revoke`).
- [x] Applications credentials — add the missing "Rotate" control (`credentials.rotate`, re-displays the one-time secret).
- [x] Projects — wire "New Project", edit, and archive (`projects.store` / `update` / `destroy`).
- [x] Agents — assemble the Create Agent wizard payload and submit it (`agents.store`), wire "Publish Agent" (`agents.publish`), and edit / delete (`agents.update` / `agents.destroy`).
- [x] Tools — wire "New Tool" / "Edit" / "Archive" (`tools.store` / `update` / `destroy`) with a schema editor for the input/output contracts.
- [x] LLM Providers — wire "Add Model" plus edit, delete, and the status toggle (`llm-providers.store` / `update` / `destroy`).
- [x] Governance — persist the security-policy toggles and retention/quota settings (`governance-settings.update`).
- [x] Governance — add a "Request approval" trigger (`approvals.store`).
- [x] Governance — add quota management (a Rate Limits tab with create / edit / delete via `quotas.store` / `update` / `destroy`, reading the `maac.quotas` prop).
- [x] Surface server-side validation errors inline, render success toasts, show the one-time credential secret on generate/rotate, and refresh list/detail views after every mutation.

### Deliverables

- A fully interactive management console: every action button performs its real create / update / delete / generate / rotate / revoke / publish / request-approval / quota / settings operation.
- Reusable form helpers (`resources/js/maac/forms.tsx`: enum option lists, `ChipMultiSelect`, `FieldError`, current-team accessor), a shared `ToolFormModal` with a JSON-schema editor, and a global `CredentialSecretGate` for the one-time secret.
- Additive API surface (`uuid` on the entity resources; safe `credentials` records on `ApplicationResource`) so the React forms can submit the ids the FormRequests validate without changing those requests.

### Acceptance Criteria

- No remaining dead action buttons on the console; each wired control performs its real action verified live in the browser with no console errors.
- Validation errors display inline, success toasts render, and the one-time credential secret is shown on generate and rotate.
- List and detail views reflect mutations immediately via the reloaded shared `maac` prop.
- `composer ci:check` stays green (ESLint, Prettier, `tsc`, PHPStan L7, Pint, Pest) with coverage held at 100 %.

## Phase 8: Production Pilot & Remaining Execution Modes

> **Status: Planned** — Phase 8 turns the completed MAAC platform into a production-pilot-ready release while closing the only documented execution-mode gap that remains after Phase 6G: read-only database (`db`) tools. Phase 8A should be implemented only for approved analytics/reporting use cases where a client-side, remote HTTP, MCP, or knowledge tool is not the safer fit. Phase 8B should be run before any broad rollout, even if Phase 8A is deferred for the first pilot.

### Phase 8A: Governed Read-Only Database Tools

> **Status: Planned** — This phase implements the BRS-listed read-only database execution mode for tightly controlled analytics and reporting use cases. It must not weaken the core MAAC isolation principle: client-side tools still keep application-owned operational data access inside the owning application. MAAC-hosted database access is allowed only through approved read-only views, replicas, or curated query surfaces with explicit governance, vault-backed credentials, traceability, and result minimization.

#### Goal

Add a governed `db` tool execution mode that lets selected agents query approved read-only data sources under strict policy controls, while preserving schema validation, auditability, redaction, quotas, approval gates, and environment separation.

#### Checklist

- [ ] Add read-only data source registration with owner, environment availability, sensitivity, business purpose, connection type, and approval status.
- [ ] Store database credentials through the secrets vault only; never persist plaintext connection strings, usernames, passwords, certificates, or tokens.
- [ ] Restrict connections to approved read-only replicas, materialized views, reporting schemas, or curated database views; block unrestricted production database access.
- [ ] Add query governance: allowlisted views/tables, statement type enforcement, blocked keywords, parameter binding, row limits, timeout limits, result-size limits, and explain-plan or dry-run validation where supported.
- [ ] Add a `db` tool configuration model that maps a tool contract to an approved data source, query template or query-builder definition, input bindings, output schema, redaction rules, and freshness expectations.
- [ ] Execute `db` tools through a dedicated runtime executor that validates inputs, binds parameters safely, runs the query with a read-only connection, validates outputs, redacts stored results, and returns only schema-approved fields to the LLM.
- [ ] Add approval gates for production `db` tools, covering data owner approval, security review, query surface, sensitivity, retention, and maximum result scope.
- [ ] Add runtime safeguards for unauthorized data sources, disabled sources, unsafe query shapes, timeout, too many rows, oversized payloads, invalid output schema, stale replicas, and connection failures.
- [ ] Surface read-only data sources and `db` tool configuration in the console with clear warnings that this mode is for governed reporting data, not application-owned transactional access.
- [ ] Update the SDK manifest, SDK docs page, integration guide, and compatibility matrix so external apps can distinguish `db` tools as MAAC-executed server-side tools.
- [ ] Add focused feature, runtime, policy, approval, and E2E tests proving successful `db` execution plus every controlled failure path.
- [ ] Keep `composer ci:check`, `php artisan test --coverage --min=100 --compact`, `maac:sdk-fixtures --check`, SDK tests, and browser smoke coverage green.

#### Deliverables

- Read-only data source registry with vault-backed credentials and environment-specific governance.
- `db` tool contract configuration and runtime executor.
- Approval, trace, audit, redaction, quota, retention, and controlled-failure coverage for database-backed tools.
- Console and SDK documentation updates that make the safety boundary explicit.

#### Acceptance Criteria

- A platform admin can register an approved read-only data source without exposing secrets in the database, API payloads, logs, or UI.
- A project owner can create a governed `db` tool that only queries approved reporting surfaces and returns schema-valid, minimized results.
- A published agent can execute a `db` tool through the runtime with complete trace, audit, quota, retention, redaction, and cost metadata.
- Unsafe SQL, non-read-only access, disabled sources, excessive rows, oversized results, invalid outputs, and unauthorized environments fail safely with controlled error codes.
- External SDK consumers see `db` tools as MAAC-executed server-side tools and do not need to implement local handlers for them.

### Phase 8B: Production Pilot Readiness & Release Baseline

> **Status: Planned** — This phase validates the complete MAAC platform against a real pilot rollout. It should produce a release baseline that security, platform, application, and business stakeholders can sign off. The goal is not to add broad new product scope; it is to prove the implemented BRS workflows under realistic configuration, operational controls, external integration, and failure conditions.

#### Goal

Prepare MAAC for the first production pilot by freezing the public integration contract, validating the full management/runtime/SDK path with at least one real consuming application, and producing evidence for security, operations, support, and stakeholder sign-off.

#### Checklist

- [ ] Select and document the pilot application, pilot project, pilot agent, approved tools, environments, LLM providers, owners, and success metrics.
- [ ] Freeze the v1 runtime and SDK public contract: OAuth token exchange, manifest sync, implementation reporting, run creation, status retrieval, tool result submission, async/polling/streaming/webhooks, compatibility negotiation, and server-side tool metadata.
- [ ] Run the full happy path through a real pilot application: credential generation, SDK installation, manifest sync, handler reporting, run invocation, pause/resume, final response, trace review, audit review, dashboard review, and incident rollback.
- [ ] Validate all supported execution modes needed by the pilot: MAAC-hosted, client-side, remote HTTP, MCP connector, knowledge/RAG, and `db` if Phase 8A is included in the pilot.
- [ ] Validate enterprise controls in pilot configuration: SSO role mapping, project membership, secrets vault rotation, model routing fallback, runtime approval, break-glass freeze, audit export, retention, masking, quota enforcement, and webhook replay.
- [ ] Run performance and reliability smoke tests for synchronous runs, async runs, streaming, polling, webhook delivery, connector calls, RAG retrieval, and the highest-risk pilot tools.
- [ ] Run security review checks for OAuth scopes, credential revocation, secret leakage, SSRF controls, connector auth, webhook signatures, audit completeness, retention settings, role matrix, and data-minimization rules.
- [ ] Create an operations runbook for deployment, seed/setup, environment variables, queue workers, scheduler, failed jobs, webhook replay, incident response, audit export, SDK troubleshooting, and model-provider outage handling.
- [ ] Create pilot onboarding documentation for application developers, project owners, security reviewers, auditors, and support operators.
- [ ] Update the BRS from "Draft for Review" to an implementation-backed release baseline, noting which BRS items are complete, deferred, or intentionally out of scope.
- [ ] Tag the release candidate, publish SDK package artifacts or internal package references, and record the exact MAAC app commit, SDK versions, migration state, and contract fixture checksum.
- [ ] Run and record the final release gate: `composer ci:check`, `php artisan test --coverage --min=100 --compact`, SDK fixture checks, SDK package tests, E2E/reference tests, browser smoke, and pilot integration smoke.

#### Deliverables

- Production pilot readiness report with pass/fail evidence for the full BRS workflow.
- Frozen v1 public API and SDK compatibility baseline.
- Pilot application integration proof and rollback plan.
- Operations, support, security-review, and onboarding documentation.
- Updated BRS release baseline and release-candidate tag.

#### Acceptance Criteria

- A real pilot application can complete the agreed MAAC workflow end to end using documented SDK/public APIs only.
- Security and operations reviewers can verify identity, secrets, access control, data handling, auditability, failure handling, and incident response from documented evidence.
- The SDK/API contract is frozen for the pilot and backed by shared fixtures, compatibility checks, changelog entries, and migration notes.
- The final release gate passes with 100 % required coverage, green static analysis, green SDK tests, green E2E/reference tests, and a successful browser/pilot smoke.
- Stakeholders have a clear list of shipped, deferred, and out-of-scope BRS items before production pilot approval.

## Public Interfaces To Preserve

### Management UI

- MAAC management pages live in the authenticated Inertia application.
- React pages should use existing app layout, Tailwind CSS, shadcn-style primitives, and lucide icons where appropriate.
- Frontend navigation and form/action wiring should use Wayfinder-generated route helpers rather than hardcoded URLs.
- Initial MAAC roles may be mapped onto the existing user/team scaffold until dedicated MAAC RBAC is implemented.

### Runtime And SDK API

- `POST /api/v1/agents/{agent_slug}/runs`
  - Creates an agent run for a registered application.
  - Returns either a completed response, a `requires_tool` response, or a controlled failure response.
- Run status retrieval
  - Lets applications and SDKs inspect queued, running, waiting, completed, failed, expired, or cancelled runs.
- `POST /api/v1/runs/{run_id}/tool-results`
  - Submits client-side tool results for a paused run.
  - Validates tool call identity, caller authorization, payload size, status, and output schema before resume.
- SDK manifest sync
  - Lets applications fetch available agents, required client-side tools, contract versions, schema definitions, and implementation requirements.
- SDK implementation status reporting
  - Lets applications report local handler names, tool versions, runtime language, validation status, and last validation timestamp.

### Core Entities

- Application
- Project
- Credential
- LLM Provider
- Agent
- Agent Version
- Tool Contract
- Tool Assignment
- Tool Implementation
- Agent Run
- Tool Call
- Trace Event
- Audit Event

## Cross-Phase Engineering Defaults

- Use Laravel migrations, models, form requests, policies, factories, seeders, controllers, and action/service classes following local conventions.
- Keep application-owned data isolated. MAAC must not directly access production application databases for client-side tools.
- Validate all management writes through form requests and authorize through policies or gates.
- Prefer Eloquent relationships, casts, scopes, and resources over ad hoc arrays once moving beyond Phase 1 mock data.
- Store secrets using hashed or vault-backed values. Never persist client secrets in plaintext.
- Keep tool input/output schemas versioned and validate runtime arguments/results at every boundary.
- Capture audit events for administrative changes and runtime events from the start of persistent implementation.
- Add focused Pest feature tests with each phase. Use browser smoke tests for major Inertia screen coverage.
- Keep user-facing docs current as part of the phase that ships a capability. When a phase implements something listed as "Coming soon" in the SDK docs, update the SDK docs page (`resources/js/pages/maac/sdk-docs.tsx`) and the `docs/MAAC_SDK_Integration_Guide.md` compatibility matrix in the same phase — move it from "Coming soon" to Supported and add usage/examples. **User-facing docs must never reference internal phase numbers** (say "Coming soon", not "Phase 6D/6E").

## Validation For This Document

- [ ] Confirm this file exists at `docs/MAAC_Phased_Implementation_Plan.md`.
- [ ] Confirm every implementation phase includes a `Goal` section.
- [ ] Confirm every implementation phase includes a Markdown checklist.
- [ ] Run `git diff --check` to catch whitespace and formatting issues.
- [ ] No application test suite is required for this docs-only change.
