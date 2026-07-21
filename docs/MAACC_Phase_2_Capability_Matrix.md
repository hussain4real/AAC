# MAACC Phase 2 Authoritative Capability Matrix

> Status: Implemented baseline for tenant/project access. This matrix is executable through `MaaccAccess`, model policies, scoped Inertia props, and the project-access lifecycle. Remaining Phase 2 control groups are tracked in the remediation plan.

## Decision rules

1. Team membership selects a tenant but grants no MAACC object access by itself.
2. Team Owner or Admin is the tenant MAACC Platform Admin and holds every tenant `MaaccPermission` for that team.
3. Every other tenant capability comes from an active `project_members` access term. Revoked, expired, null-role, foreign-tenant, or missing terms fail closed.
4. Project roles never grant a global `PlatformRole`. `platform_admin` cannot be assigned as a project role.
5. Global platform-operator roles are separate, permission-specific, and cross-tenant only within their documented `PlatformPermission` remit. Except for the Super Admin gate override, a platform role never implies an unrelated permission.
6. Server policies authorize routes and actions. The server-issued `auth.maacc` snapshot controls presentation only and cannot grant access.
7. The shared console corpus is projected before delivery. Project actors receive only objects related to active project memberships. Team-wide control planes with no safe project scope return no records to project actors.
8. A wrong-tenant bound object returns 404. A correctly owned object without the required capability returns 403.

## Tenant/project role matrix

| Role | Scope | View objects | Change objects | Approval/audit | Project access administration | Navigation |
|---|---|---|---|---|---|---|
| Tenant Platform Admin | Current team | All tenant objects and safe fields | Applications, projects, agents, tools, credentials and control-plane settings | Publish, approve, audit, security review | Assign, change, expire, certify and revoke every project term | All tenant console areas |
| Project Owner | Active assigned projects | Assigned projects plus related applications, agents, runs, tools and integrations | Project, agent and tool configuration in assigned projects | Publish agents; approve tools; no tenant-wide audit export | Assign, change, expire, certify and revoke access in owned projects | Build, integrate, validate, run, project governance and settings |
| Developer | Active assigned projects | Assigned projects and related development/runtime objects | Agents and tools in assigned projects | May request governed changes; cannot approve/publish | None | Projects, agents, tools, SDK, journey, playground, knowledge, evaluations, runs, settings |
| Viewer | Active assigned projects | Read-only project, agent, tool and run views | None | None | None | Dashboard, projects, agents, tools, runs, settings |
| Auditor | Active assigned projects | Read-only assigned project objects and their evidence | None | View audit evidence; export only when an export policy independently authorizes it | None | Read surfaces, runs, governance and settings |
| Security Reviewer | Active assigned projects | Assigned project security, tool and evidence surfaces | No general editing | View audit, review security and approve tools | None | Security-relevant build, integration, run and governance surfaces |
| No active role | Current team only | No MAACC object corpus | None | None | None | None |

## Capability-to-enforcement map

| Capability | Route/action enforcement | Object/query enforcement | Field/prop enforcement | Navigation |
|---|---|---|---|---|
| `view` | `viewAny` and `view` policies; detail controllers authorize bound objects | Active project IDs constrain applications, projects, agents, runs, tools, models, webhooks and evaluation data | Unauthorized team-wide audit, vault, identity, incident, routing and governance records are not delivered | Server snapshot emits only role-approved screens |
| `project:manage` | Project create/update/archive and project-member lifecycle policies | Target application/project must belong to current tenant; membership parent must match | Member directory and membership lifecycle fields are delivered only to project managers | Project access control shown only when `can.manageMembers` is true |
| `agent:manage` | Agent create/update/delete and console run policy | Agent must belong to an active authorized project | Create/run controls hidden without permission | New Agent and Playground available only when issued |
| `agent:publish` | Agent publication policy | Publication remains project-scoped | Publish controls must use policy result | No independent navigation grant |
| `tool:manage` | Tool create/update/delete policy | Application-owned tools require an authorized project in that application; global tools require use by an authorized agent | Secret material is never serialized | Tool development surfaces only |
| `tool:approve` | Tool approval and approval-decision policies | Approval subject must remain inside actor scope | Approval queue must be independently scoped | Governance only for eligible roles |
| `audit:view` | Audit policy and export policy | Evidence must be team/project scoped before delivery | Payload masking and retention rules still apply | Governance/runs for Auditor and Security Reviewer |
| `security:review` | Security-review policies | Assigned project scope | Only safe evidence fields | Security-relevant governance surfaces |

## Project-access lifecycle

| Operation | Required authority | Required input | Persisted evidence | Repeat behavior |
|---|---|---|---|---|
| Assign | `project:manage` on target project | Team member, project role, optional future expiry, reason | Grantor, role, expiry, reason, audit event | Existing inactive term is reactivated with a new evidence term |
| Change role/expiry | `project:manage` | New role, optional future expiry, reason | Previous/new role, grantor, expiry, reason, audit event | Locked update prevents competing writes from silently interleaving |
| Certify | `project:manage` | Certification note | Certifier, timestamp, note, audit event | Active terms only; inactive terms return conflict |
| Revoke | `project:manage` | Reason | Revoker, timestamp, reason, audit event | Idempotent; retained record remains listable as evidence |
| Expire | Clock | Stored expiry | Original grant evidence remains | Authorization and shared capabilities fail closed without client traffic |

## Global platform-operator remit

The global roles in `PlatformRole` are not tenant project roles:

| Global role | Cross-tenant remit |
|---|---|
| Super Admin | All platform permissions, including break-glass and role assignment; gate override is explicit and audited |
| Platform Admin | All platform permissions except break-glass activation |
| Security Reviewer | Security review, approvals, audit/export and incident containment; no unrestricted user or configuration management |
| Auditor | Read-only platform evidence plus audit export |
| Support Operator | Application/run/webhook investigation and webhook operations only |
| Release Manager | Agent/model/tool approval, SDK release and related run/audit visibility |
| Read-only Observer | Permissions whose action is `view`; no export or mutation |

## Required regression evidence

- Every role and no-role case must cover shared props, navigation, direct URLs, object mismatch, actions and exports.
- Current-team switching must recompute the server snapshot; no capability is accepted from browser storage.
- Revoked and expired terms must remove access on the next request and remain visible to authorized reviewers.
- Cross-tenant subject selection, parent/membership mismatch, and project-scoped Platform Admin assignment must fail.
- Hidden controls cannot substitute for policy checks, and unauthorized records cannot appear in page props or browser history.
