# MAAC Platform Administration

> MAAC's own internal administration model (Phase 8B): a dedicated, global platform-administration RBAC layer backed by `spatie/laravel-permission`. Audience: MAAC platform admins, security reviewers, and operators.

## The model in one paragraph

MAAC has **two** authorization layers. The **tenant RBAC** (team `Owner`/`Admin`/`Member` + project `MaacRole`) governs a team's own application workflows and is unchanged. The **platform RBAC** described here is a *global* layer held by MAAC's own operators that governs the platform itself — managing other users' access, cross-tenant governance, audits, and operational sign-off. **A user with no platform role is a pure tenant user** and can never reach the platform-admin surfaces. The two are independent: holding a platform role does not change a user's tenant workflows, and tenant roles never confer platform administration.

## Roles

Seven global platform roles (`App\Enums\PlatformRole`), each granting a curated subset of the permission catalogue:

| Role | Remit |
| --- | --- |
| **Super Admin** | Unrestricted platform control. Holds an authorization **override** (a `Gate::before` that passes every gate, including cross-tenant), and is the only role that can assign Super Admin or activate break-glass. |
| **Platform Admin** | Full operational control of the platform, tenants, and governance — everything except emergency break-glass. |
| **Security Reviewer** | Review/decide approvals, approve tools, read & export audits, and run incident containment. |
| **Auditor** | Read-only across the platform plus signed audit export. |
| **Support Operator** | Investigate runs and replay webhooks to support tenant applications. |
| **Release Manager** | Promote agents/models and manage SDK distribution and release approvals. |
| **Read-Only Observer** | Read-only visibility, no change or export rights. |

## Permissions

33 granular permissions (`App\Enums\PlatformPermission`), named `domain.action` (e.g. `applications.manage`, `audits.export`, `roles.assign`, `breakglass.activate`), spanning platform users, teams, applications, projects, agents, tools, models, credentials, approvals, quotas, runs, audits, webhooks, SDK operations, incidents, settings, access review, and break-glass. A role's permission set is the source of truth in the enum and is seeded into Spatie by `PlatformRbacSeeder`.

Checks compose through Laravel's gate: `$user->hasPlatformPermission(PlatformPermission::ViewAudits)` (and the spatie `permission:` route middleware, whose `canAny()` goes through the gate) both honour the Super Admin override.

## Bootstrapping & mapping users

- **Bootstrap Super Admins** by email so the platform is never left without an administrator: `MAAC_PLATFORM_SUPER_ADMINS="ops@milaha.com,security@milaha.com"`. Empty by default in production — assign through the console or SSO instead.
- **SSO group mapping**: add a `platform_role` to an SSO connection's `group_role_mappings` entry; on login `SsoUserResolver` idempotently grants the mapped platform role and records a grant. A tenant user gets **no** platform role unless a group is explicitly mapped.
- **The console**: a Super Admin or Platform Admin grants/revokes roles on the **Access Control** page (Govern group), which appears only for users holding the real `users.view` platform permission.

## Audited grant management

Spatie's `model_has_roles` is the authorization source of truth; the `platform_access_grants` ledger + `PlatformAccessManager` are the governance trail around it. Every action writes a `platform_access.*` audit event (actor, target, role, reason, scope):

- `grant` — a deliberate, certified role assignment.
- `breakGlass` — time-boxed emergency access (see below).
- `revoke` — removes the role unless another active grant still holds it.
- `certify` — re-certifies a grant during access review.
- `syncSsoRole` — idempotent SSO-driven grant (system-attributed).

## Break-glass emergency access

For incidents, a Super Admin can grant **time-boxed** emergency platform access. The TTL is clamped to `config('maac.platform.break_glass')` (`default_ttl_minutes`, `max_ttl_minutes`). The grant auto-expires; the scheduled review (below) revokes any elapsed break-glass grant and removes the role.

## Access review

`maac:review-platform-access` (scheduled daily) is the periodic certification + cleanup:

```bash
php artisan maac:review-platform-access          # revoke expired break-glass; report review work
php artisan maac:review-platform-access --json   # machine-readable
```

It (1) revokes elapsed break-glass grants, (2) lists **standard grants needing re-certification** (not certified within `access_review.certification_days`), and (3) lists **stale admin accounts** — holders of an old grant with no audited activity within `access_review.stale_days`. The Access Control page surfaces the same three work lists with one-click certify.

## Configuration

`config('maac.platform')`:

| Key | Purpose | Default |
| --- | --- | --- |
| `super_admins` | Bootstrap Super Admin emails (`MAAC_PLATFORM_SUPER_ADMINS`) | `[]` |
| `break_glass.default_ttl_minutes` | Default emergency-access window | `60` |
| `break_glass.max_ttl_minutes` | Hard cap on the window | `240` |
| `access_review.certification_days` | Re-certification window for a standard grant | `90` |
| `access_review.stale_days` | Inactivity window after which an admin is flagged stale | `60` |

## Operations notes

- Run `maac:review-platform-access` on a schedule (it is registered in `routes/console.php`) so break-glass never lingers.
- Tenant data boundaries are unchanged: a platform role grants MAAC administration, not direct access to a tenant application's data — application-owned tools still run in the owning application.
- The Super Admin override is intentionally broad; keep the Super Admin set small and review it via the access-review report.
