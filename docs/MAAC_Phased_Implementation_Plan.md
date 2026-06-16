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

### Goal

Prove the core MAAC integration model: tool contracts are created first in MAAC, then application teams implement compatible local handlers through an SDK.

### Checklist

- [ ] Build the Tool Registry as the source of truth for global, project-level, and agent-level tool contracts.
- [ ] Support initial execution modes required for MVP: MAAC-hosted, client-side, remote HTTP, and knowledge retrieval as contract options, with only MAAC-hosted and client-side needing full runtime behavior in this phase.
- [ ] Add JSON input schema and output schema validation for tool contracts.
- [ ] Track client-side tool implementation status per application environment: not required, requires implementation, implemented, outdated, incompatible, disabled.
- [ ] Add tool contract versioning with compatibility checks between current contract version and reported SDK implementation version.
- [ ] Build SDK Implementation Center data from real contracts, assignments, application environments, and reported implementations.
- [ ] Generate TypeScript and PHP SDK handler stubs from tool contracts.
- [ ] Implement application credential authentication for SDK/API access using project/application-scoped credentials and short-lived tokens or signed requests.
- [ ] Implement SDK manifest sync endpoints for applications to fetch required tools and report implemented handlers.
- [ ] Add controlled errors for missing, outdated, incompatible, disabled, or unauthorized tool handlers.
- [ ] Add tests for schema validation, implementation status transitions, credential auth, manifest sync, and stub generation.

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

### Goal

Deliver the first real agent run lifecycle, including secure invocation, approved LLM calls, tool-call detection, client-side pause/resume, trace events, and final response payloads.

### Checklist

- [ ] Implement `POST /api/v1/agents/{agent_slug}/runs` for authenticated application/SDK invocation.
- [ ] Implement run status retrieval for applications and SDKs.
- [ ] Implement `POST /api/v1/runs/{run_id}/tool-results` for client-side tool result submission.
- [ ] Authenticate and authorize each run against application credentials, project membership, agent publication status, environment, and model/tool policies.
- [ ] Create Agent Run records with statuses: queued, running, requires_tool, waiting_for_client, completed, failed, expired, and cancelled.
- [ ] Create Tool Call records whenever the runtime requests a tool.
- [ ] Integrate the first approved LLM provider through an LLM Router abstraction.
- [ ] Pass agent prompt, selected model, runtime settings, caller context, and assigned tool definitions into the runtime.
- [ ] Detect model-requested tool calls and route them by execution mode.
- [ ] Execute MAAC-hosted tools inside the platform for simple built-in utilities.
- [ ] Pause runs for client-side tools and return a structured `requires_tool` response to the SDK.
- [ ] Validate submitted tool results against output schema before resuming the run.
- [ ] Resume the LLM call with validated tool results and compose a final response.
- [ ] Enforce timeouts, payload size limits, retry limits, cancellation, and safe failure responses.
- [ ] Record trace events for run requested, caller authenticated, model selected, prompt prepared, tool required, tool result received, validation, resume, completion, and failure.
- [ ] Add tests for no-tool runs, client-side pause/resume runs, invalid tool results, expired runs, unauthorized invocations, and failed model/tool calls.

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

### Goal

Make MAAC auditable, governable, and enterprise-safe for controlled production usage.

### Checklist

- [ ] Build run and audit dashboards from Agent Run, Tool Call, Trace Event, usage, cost, and status data.
- [ ] Track token input, token output, estimated cost, latency, model, provider, tool usage, failure reason, and caller context.
- [ ] Implement configurable prompt, response, tool argument, and tool result retention policies.
- [ ] Implement masking/redaction behavior for sensitive tool inputs and outputs.
- [ ] Add data sensitivity classifications for tools, agents, runs, models, and logs.
- [ ] Add approval queues for sensitive tool contracts, agent publication, model access, and production credential changes.
- [ ] Add credential rotation, revocation, audit history, and last-used metadata.
- [ ] Add rate limits and quotas by application, project, agent, model, and environment.
- [ ] Add environment separation controls for credentials, agents, tool implementations, model availability, logs, and retention settings.
- [ ] Add operational monitoring hooks for error rate, waiting runs, expired runs, average latency, cost anomalies, and tool failure rate.
- [ ] Add authorization tests for Platform Admin, Project Owner, Developer, Viewer, Auditor, and Security Reviewer roles.
- [ ] Add audit tests for key administrative and runtime events.

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

### Goal

Extend MAAC beyond the MVP while preserving the same governance, data isolation, observability, and SDK-first integration model.

### Checklist

- [ ] Integrate enterprise SSO/IAM for web users, mapped to MAAC roles and project/application membership.
- [ ] Integrate a secrets vault for LLM provider keys, application credential material, remote tool secrets, and connector credentials.
- [ ] Add asynchronous runtime mode for long-running agent runs.
- [ ] Add streaming runtime events for chat-style or progress-oriented interfaces.
- [ ] Add polling and webhook SDK integration modes.
- [ ] Implement remote HTTP tools with allowlisted endpoints, method constraints, auth configuration, retries, and response validation.
- [ ] Implement connector server support for application-owned advanced integrations.
- [ ] Implement knowledge retrieval/RAG tools with approved document sources, indexing pipeline, citation metadata, and access controls.
- [ ] Add evaluation lab capabilities for prompt, tool, model, regression, and safety testing.
- [ ] Add advanced model routing policies for sensitivity, cost, latency, fallback, and environment.
- [ ] Add human-in-the-loop approval steps for sensitive runtime actions.
- [ ] Add SDK support for additional priority stacks beyond the first implementation language.
- [ ] Add migration guides, deprecation windows, and compatibility dashboards for tool contract version changes.

### Deliverables

- Enterprise identity and secrets integrations.
- Additional runtime modes for async, streaming, polling, and webhooks.
- Expanded tool ecosystem: remote HTTP, connector server, and knowledge retrieval.
- Evaluation and advanced governance capabilities.
- Multi-language SDK roadmap and compatibility tooling.

### Acceptance Criteria

- Enterprise authentication and secret storage replace local placeholders without changing MAAC's product model.
- Long-running and streaming agent experiences work without weakening auditability or timeout controls.
- Remote, connector, and knowledge tools follow the same schema validation, policy, versioning, and trace standards as client-side tools.
- Evaluation workflows can compare agent behavior across prompts, models, tools, and versions before production rollout.

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

## Validation For This Document

- [ ] Confirm this file exists at `docs/MAAC_Phased_Implementation_Plan.md`.
- [ ] Confirm every implementation phase includes a `Goal` section.
- [ ] Confirm every implementation phase includes a Markdown checklist.
- [ ] Run `git diff --check` to catch whitespace and formatting issues.
- [ ] No application test suite is required for this docs-only change.
