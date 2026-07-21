# MAACC Phase 3 Engineering Evidence and Enterprise Readiness Gap Report

**Assessment date:** 19 July 2026

**Change owner:** Aminu Hussain

**Assessed branch:** `codex/phase-3-enterprise-readiness`

**Source requirements:** `docs/MAACC_BRS(1).md` and `docs/MAACC_Enterprise_Readiness_Remediation_Plan.md`
**Environment classification:** Demo only; no real customer or sensitive production data

## Executive decision

Phase 3 is **engineering-remediated but not enterprise-accepted**.

The implementation now closes the highest-risk product-truth problems: global console over-fetching, fixture-backed reporting, ambiguous currency, dead or misleading controls, non-responsive navigation, inaccessible shared interactions, starter-page identity drift, and health checks that could pass with missing frontend assets. Local automated tests, static checks, production builds, 10k/100k run benchmarks, and browser checks support those conclusions.

The readiness rating remains **AMBER**. Engineering remediation is complete, but MAACC must not be represented as enterprise-ready or approved for real data because the following independent/manual acceptance evidence does not yet exist:

1. independent finance and operations reconciliation approval;
2. a browser mutation matrix covering every primary/destructive action and 403/409/422/429/network/5xx/duplicate-submit outcomes;
3. manual VoiceOver, large-text, 200% zoom, and both-theme acceptance;
4. signed G4 and the Phase 3 portions of G5/G6.

The current production-like site is a demo. Its absence of real data reduces migration and privacy exposure during remediation, but it does **not** prove production capacity, operational resilience, accessibility acceptance, financial correctness, or safe real-data onboarding.

## What was reviewed

- BRS functional requirements FR-001 through FR-056 and all non-functional categories.
- Phase 3 gaps ER-10 through ER-13 and edge cases EC-22, EC-27 through EC-36, and EC-40.
- Laravel authorization, query construction, resources, cache invalidation, reporting and readiness paths.
- Inertia/React page contracts, mutations, secrets, Playground state, form safety and error delivery.
- Shared shell, component semantics, responsive grids, chart edge cases, contrast and product identity.
- PR #51 review feedback and the current Graphify artifacts.
- Automated PHP/TypeScript gates, 10k/100k data-volume tests and authenticated browser behavior.

## Implemented controls and evidence

### 1. Page data architecture and scale

| Control | Implementation evidence | Verification | Result |
|---|---|---|---|
| Page-scoped authorized contracts | `app/Support/MaaccConsolePageData.php`; page closures in `app/Http/Controllers/Maacc/ConsoleController.php` and `app/Http/Controllers/DashboardController.php`; global `maacc` corpus removed from `HandleInertiaRequests` | `console pages receive only their authorized page contract`; project actor SQL-scope test | Closed |
| Bounded high-volume runs/tools/governance/webhooks/incidents | Cursor pagination, bounded page sizes and server search/filter methods in `MaaccConsolePageData` | Cursor/filter tests; response cap | Closed for implemented collections |
| Tenant-scoped cache invalidation | `app/Support/MaaccConsoleCache.php` uses tenant version keys and model-driven invalidation | Cross-tenant cache-version test | Closed |
| Query plans and indexes | `2026_07_19_071642_add_reporting_currency_and_console_indexes.php` | Schema assertions and SQLite `EXPLAIN QUERY PLAN` requiring `agent_runs_app_status_created_index` | Closed |
| Volume budgets | 20 authenticated samples at 10k and 100k run rows, 25-row page | `MAACC_PERFORMANCE_ROWS=10000` and `100000` performance tests | Closed for the Runs page |
| Platform administration scale | Cursor-bounded administrators/audit, searchable user directory, 100-row review caps, SQL stale-grant anti-join, supporting indexes | 10,000 administrators/grants: 25-row response, cursor present, ≤15 queries, <400 KB | Closed |
| Team membership scale | Cursor-bounded membership relation with a 100-row hard maximum | Deferred-prop and 40-member pagination tests | Closed |
| Secondary-data delivery | Access-control and settings member data use grouped `Inertia::defer(..., rescue: true)` contracts | Initial-prop absence, deferred reload, loading/error/retry UI and TypeScript build | Closed |

Enforced benchmark budgets are: at most 45 queries, at most 400 KB response content, server p50 at most 750 ms, p95 at most 1,500 ms, p99 at most 2,000 ms, and at most 128 MB peak-memory growth. Both 10k and 100k runs passed. The 100k test completed in 2.368 seconds including database generation and 20 requests; it is not a production load-test measurement.

The authenticated local browser matrix sampled Dashboard, Applications, Create Agent, Runs, Playground and Governance at 320px and 1440px. Its slowest navigation plus a deliberate 350 ms stabilization delay was 705 ms. This is useful regression evidence, not a substitute for target-concurrency or production-region performance testing.

### 2. Reporting and operational truth

| Control | Implementation evidence | Verification | Result |
|---|---|---|---|
| Governed price provenance | Currency, source, version and effective timestamp on provider quotes and persisted runs | `ReportingReconciliationTest` | Closed |
| Authoritative rollups | `RunMetrics` derives usage, cost, success/failure, users and departments from `agent_runs` | Known-fact reconciliation dataset | Closed |
| Privacy-minimized caller reporting | Caller subject and department are one-way keyed before display | Tests reject raw subject and department values | Closed |
| Timestamp truth | Run resources expose ISO-8601 started/completed and pricing-effective timestamps | Atomic timestamp assertions | Closed |
| Application versus platform health | Dashboard states metric source/freshness/cache separately; `/up` checks DB and promoted assets through `ProductReadinessProbe` | Entry/readiness tests | Closed |
| Zero/one chart safety | Shared charts handle empty and one-point denominators without invalid coordinates | Static/type/build and focused rendering review | Closed |

All monetary values are explicitly shown as estimated source-currency values. No currency conversion is implied. Independent finance and operations approval remains open.

### 3. Authoritative actions and recovery

Implemented changes include:

- removed the fake Dashboard export, SDK re-validation action, global search and global environment selector;
- made the Playground environment explicit and limited to the governed test run;
- routed Settings documentation to the real SDK documentation and removed the inert contact action;
- made trace copying asynchronous with success/failure truth and a manual fallback;
- made credential and webhook secrets non-dismissible until explicit acknowledgement;
- made Create Agent selections use real page data, clear stale dependencies, persist sensitivity and production-approval policy, and remove unsupported per-agent governance toggles;
- added dirty-form protection and Playground request cancellation/identity isolation;
- generated run and tool-result endpoint examples from Wayfinder route metadata;
- added stable schema-row identity with exact duplicate/empty-key errors;
- standardized global Inertia HTTP/network messaging for 403, 409, 429, network and server failures;
- removed false “all systems operational” language from the console sidebar.

Focused automated evidence exists in `AgentManagementTest`, component source-contract tests, route generation/type checks and the production build. A complete browser failure-state matrix for every destructive action is still open, so the broad ER-12 acceptance item is not marked complete.

### 4. Responsive and accessible foundations

Implemented changes include:

- `100dvh` shell, mobile menu, overlay navigation, Escape close, skip link and visible keyboard focus;
- one- and two-column responsive stat/layout rules with mobile filter/action wrapping;
- keyboard-activatable rows/cards, tablist/tab semantics and arrow/Home/End tab navigation;
- accessible names for icon buttons, filters, selects and view toggles;
- switch semantics, clipboard live feedback, reduced-motion support and horizontal table containment;
- corrected light/dark foreground-background token pairs with an automated 4.5:1 minimum contrast contract.

Browser results:

| Viewport | Horizontal document overflow | Navigation behavior | Core route result |
|---:|---:|---|---|
| 320 | None | Desktop sidebar hidden; menu visible; opens and closes with Escape | Pass |
| 375 | None | Mobile shell | Pass |
| 768 | None | Desktop shell; corrected two-column stat layout | Pass |
| 1024 | None | Desktop shell | Pass |
| 1440 | None | Desktop shell | Pass |

At 320px, wide audit/tool tables remain intentionally contained within their own horizontal scroll region rather than widening or clipping the document. The final automated DOM pass on the sampled core routes found no duplicate IDs and no visible unnamed buttons or form controls. Radix's hidden `aria-hidden` compatibility selects were excluded from that visible-control count.

Pest Browser now runs axe at critical-and-serious severity across Dashboard, Applications, Projects, Agents, Tools, Runs, Governance, Settings and Access Control. It also blocks console/JavaScript/broken-image failures, checks document overflow at 320/375/768/1024/1440px, and compares deterministic full-page screenshots at all five breakpoints. This is still not full WCAG acceptance: manual VoiceOver, keyboard journey sign-off, large-text, 200% zoom and both-theme evidence remain open.

### 5. Product identity and readiness

- `/` now redirects guests to sign-in, members to their team dashboard and users without a team to team setup.
- The 5,805-line unauthoritative public prototype and its standalone stylesheet were removed.
- MAACC titles, Composer package metadata and application fallbacks replace Laravel starter identity.
- Instrument Sans is served from the promoted Vite artifact; the external Google Fonts import was removed.
- Demo seed data is restricted to local/testing and now consistently uses `Milaha Demo Workspace` with the Milaha demo persona.
- `/up` now fails when the DB probe or promoted manifest/representative application assets are unavailable.

The approved entry is an authenticated redirect rather than a new public marketing surface. Privacy/legal/security/support publication content remains a product/legal decision and is not claimed complete by the checklist.

## BRS validation

### Phase 3 affected functional requirements

| BRS requirement | Evidence reviewed | Phase 3 conclusion |
|---|---|---|
| FR-006, FR-007 | Create Agent route/action/request, real project data, required prompt/use-case validation | Implemented |
| FR-008 | Approved provider lists are team/project scoped; stale selections clear | Implemented |
| FR-011 | Governed Playground uses authoritative agents/environment and isolated request identity | Implemented; browser negative matrix open |
| FR-012 | Published-agent API paths are generated from `/api/v1` route metadata | Implemented |
| FR-016 | Governed model rates, source currency and persisted run provenance | Implemented; finance sign-off open |
| FR-021, FR-023 | Schema editor validation and implementation-state reporting | Implemented |
| FR-026 | Governance approval queues and rationale surfaces | Implemented; full failure matrix open |
| FR-043–FR-045 | Run, model, token, duration, status, trace and tool metadata resources | Implemented |
| FR-046 | Retention/masking source remains authoritative from Phase 1/2; unsupported per-agent toggles removed | Implemented |
| FR-047 | Usage/cost rollups by project, agent, model, keyed user and keyed department | Implemented; independent review open |
| FR-048 | Failed runs are filterable, paginated and inspectable | Implemented |
| FR-049 | Page data is authorized and scoped before serialization | Implemented; access-control and member directories are bounded, searchable where selection requires it, and scale-tested |
| FR-053 | Caller context is recorded and privacy-minimized for reporting/tool context | Implemented |
| FR-056 | Agent sensitivity and production approval are persisted; unsupported controls removed | Implemented within approved policy contract |

### Non-functional requirements

| Category | Finding |
|---|---|
| Security | Phase 3 reduces prop overexposure and fixture leakage. Final production security proof remains in Phases 2/4. |
| Availability | Promoted-asset/DB readiness is stronger; HA and dependency-deep health are Phase 4 work. |
| Performance | Runs pass explicit 10k/100k server/query/payload/memory budgets; production concurrency/load remains open. |
| Scalability | High-volume runs and operational collections are bounded; access reporting is proven at 10,000 administrators/grants. Production concurrency remains Phase 4 evidence. |
| Maintainability | Page query object, tenant cache, typed routes, static gates and focused tests improve change safety. |
| Observability | Reporting source/freshness/currency and application-vs-platform health are explicit. |
| Compliance | No real data is present; masked caller reporting avoids displaying raw subject/department values. Independent compliance remains Phase 4. |
| Extensibility | Route/contract-generated examples reduce SDK drift. |
| Reliability | UI request identity and secret acknowledgement close important client races; exhaustive failure-state browser proof remains open. |
| Usability | Tool implementation states, mobile shell, filters and error messages are materially improved; independent accessibility acceptance remains open. |

No reviewed Phase 3 code contradicts the BRS. The remaining gaps are evidence depth, scale completeness and independent acceptance—not permission to treat an unchecked requirement as delivered.

## PR #51 review disposition

PR #51 is merged and its checks passed. Its actionable inline review identified machine-local Graphify metadata. The current repository tracks only portable `GRAPH_REPORT.md`, `graph.html` and `graph.json` evidence; `.graphify_root`, cache, cost and manifest state are ignored, and the portable Graphify output contains no `/Users/amisha/www/maac` path. The issue is therefore closed in the current tree.

## Engineering closures completed in this branch

| Closed gap | Implementation and proof |
|---|---|
| Unbounded platform grants/member directories | Cursor contracts, server directory search, stable indexes, bounded access-review work lists, deferred rendering, 10,000-admin query/payload proof and member pagination tests |
| Deferred secondary-data behavior | Grouped Inertia v3 deferred props with explicit loading, error and retry behavior; deferred-response assertions |
| Automated axe and responsive regression | Critical/serious axe across nine routes, five overflow breakpoints and five deterministic visual baselines |
| Empty-tenant BRS journey | `ConsoleToRuntimeTest` starts from a database-clean tenant and drives member/reviewer setup, application, project, model, tool, agent, production credential, SDK implementation report, four-eyes publication, invocation, client-tool resume, trace, cost and audit assertions without demo fixtures or live providers |

## Remaining readiness gaps and closure execution plan

| Priority | Gap | Closure action | Owner/approver | Evidence to attach |
|---:|---|---|---|---|
| P0 | No independent financial/operational reconciliation | Review governed price catalog, sample invoices/usage facts, timezone and health definitions | Finance + Operations | Signed reconciliation worksheet and exceptions |
| P0 | Primary/destructive action failure matrix incomplete | Exercise success, 403, 409, 422, 429, offline, 5xx and double-submit for each high-risk mutation | QA + Engineering | Browser test report and screenshots/traces |
| P0 | Manual WCAG acceptance incomplete | Run keyboard, VoiceOver, 200% zoom, large-text, reduced-motion and both-theme journeys | Accessibility + QA | Signed manual checklist; automated critical/serious axe gate is already green |
| P1 | G4/G5/G6 signatures absent | Review this report and all closure evidence with assigned gate owners | UX/Accessibility, Engineering, Architecture/SRE | Signed gate records |

Recommended execution sequence:

1. complete the primary/destructive mutation and failure-state browser matrix;
2. perform finance/operations and manual accessibility reviews;
3. rerun the aggregate quality, 100k and browser gates from a clean checkout;
4. obtain G4/G5/G6 signatures and only then change Phase 3 from AMBER to accepted.

## Verification ledger

| Gate | Result |
|---|---|
| Focused bounded access/member tests | Pass, including 10,000 administrators/grants, 25-row cursor page, ≤15 queries and <400 KB payload |
| Clean-tenant BRS journey | Pass: 2 tests, 116 assertions; no demo fixtures or live provider dependency |
| Pest Browser enterprise suite | Pass: 7 tests, 56 assertions; nine core routes, critical/serious axe, console/script/image checks, five overflow breakpoints and five visual baselines |
| PHPStan | Pass, zero errors |
| Pint | Pass |
| ESLint | Pass |
| TypeScript | Pass |
| Production Vite build | Pass, 381 modules transformed |
| `git diff --check` | Pass |
| 10k performance profile | Pass |
| 100k performance profile | Pass; 1 test, 231 assertions, 2.305 s total |
| Browser core-route matrix | Pass for critical/serious axe, console/JavaScript logs, broken images, document overflow and navigation timing |
| Mobile menu interaction | Pass: desktop sidebar hidden, menu visible, open and Escape-close at 320px |
| Full PHP/browser aggregate suite | Pass: 1,314 tests, 1,311 passed, 3 skipped, 6,389 assertions |
| Exact PHP line coverage | Pass: 100.0% |
| Aggregate `composer ci:check` | Pass: ESLint, Prettier, app/SDK TypeScript, 33 SDK/reference tests, fixture drift, PHPStan, Pint and full PHP/browser suite |

## Go/no-go statement

**Demo continuation:** GO, with the readiness banner and no-real-data restriction retained.

**Real sensitive data, enterprise onboarding or general production:** NO-GO until every open P0 item is closed, the applicable P1 evidence is accepted, and G4/G5/G6 are signed.
