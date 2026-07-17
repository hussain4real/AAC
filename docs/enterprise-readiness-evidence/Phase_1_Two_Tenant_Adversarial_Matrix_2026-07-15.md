# Phase 1 Two-Tenant Adversarial Matrix — 15 July 2026

## Decision

The Phase 1 ER-01 two-tenant matrix is implemented and passes locally. A Tenant A actor or application credential cannot create or mutate through a Tenant B parent/object, observe Tenant B manifest or audit data, publish or execute a Tenant B agent, or directly open a Tenant B console object. MAACC currently exposes no import route; the matrix deliberately fails if an import surface is added without extending this contract.

This is automated engineering evidence. It does not replace the read-only integrity scan and Security/Data Owner approval required in every real environment.

## Matrix

| Surface | Adversarial setup | Expected control | Automated evidence | Result |
| --- | --- | --- | --- | --- |
| Create | Tenant A owner submits Tenant B application as the new project's parent | Tenant/parent-scoped validation rejects the relationship before persistence | `create rejects a foreign parent` | Passed; validation error and no injected project |
| Update | Tenant A owner addresses Tenant B agent below Tenant A's route context | Bound-object ownership middleware and policy fail closed | `update rejects a foreign object` | Passed; 404 and object unchanged |
| Import | Any future import route could introduce unreviewed bulk relationship writes | No import surface may exist until it has a tenant-scoped contract and matrix coverage | `import remains unavailable until a tenant-scoped import contract is implemented` | Passed; no named or URI import route exists |
| Manifest | Tenant A SDK credential requests its application manifest while Tenant B has published resources | SDK application context scopes all manifest resources | `manifest excludes foreign agents and tools` | Passed; Tenant B agent/tool absent |
| Publish | Tenant A owner targets a draft Tenant B agent | Object ownership is resolved before the readiness/publication transition | `publish rejects a foreign agent` | Passed; 404 and status remains draft |
| Execute | Tenant A SDK credential invokes Tenant B agent slug | Application-scoped runtime lookup returns the stable not-found envelope | `execute returns the same not-found envelope for a foreign agent` | Passed; 404 `agent_not_found` |
| Export | Tenant A owner exports audit data while both tenants have events | Export query is scoped to the selected tenant | `export contains only the selected tenants audit records` | Passed; Tenant A event present, Tenant B event absent |
| Direct object access | Tenant A owner opens Tenant B application detail slug | Implicit model binding exposes the object to selected-team ownership middleware, which returns 404 | `direct object access cannot expose a foreign application` | Passed; 404 |

## Regression proof

- Test: [`TwoTenantAdversarialMatrixTest.php`](../../tests/Feature/Security/TwoTenantAdversarialMatrixTest.php)
- Focused command: `php artisan test --compact tests/Feature/Security/TwoTenantAdversarialMatrixTest.php`
- Latest combined security run: 65 tests passed, 377 assertions, 0 failures.
- The direct-object case detected and drove a real fix: `EnsureCurrentTeamResourceOwnership` now resolves an existing application slug before the unbound application shell renders. Agent, tool, and run shells retain their established 200/null UX for unknown or foreign slugs, while their page data remains selected-team scoped and separately regression tested.

## Extension rule

Any new import, bulk operation, detail route, export, manifest resource, publish transition, or runtime execution mode must add a Tenant A → Tenant B denial case to this matrix before the surface is accepted. A route's absence may be evidence only while the corresponding capability remains intentionally unavailable.
