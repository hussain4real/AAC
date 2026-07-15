# MAACC BRS v1 Approval Baseline

**Status:** Proposed for approval — not yet approved
**Baseline family:** BRS v1
**Candidate source revision:** 1.1, dated 8 June 2026
**Prepared:** 15 July 2026
**Required approvers:** Product/Platform Owner, Architecture, Security/IAM, Privacy/Compliance, Engineering, and SRE

## Purpose

This record gives approvers one immutable candidate baseline for Phase 1 gate G0. It does not change the approval state of the source documents and is not itself an approval. MAACC remains non-enterprise until the approval table is completed by the named owners and the signed record is linked from the Phase 1 ledger.

The term **BRS v1** means the major-version-one requirements family. The current candidate is revision 1.1; approval must not silently relabel it as revision 1.0.

## Candidate document set

| Artifact                                                                                                                                       | Candidate version/status   | SHA-256 at preparation                                             | Role in baseline                                            |
| ---------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------- | ------------------------------------------------------------------ | ----------------------------------------------------------- |
| [`MAACC_BRS(1).md`](<MAACC_BRS(1).md>)                                                                                                         | 1.1; Draft for Review      | `50fb1381a4c14b46d7ff8472571a003d8b9b7decab680ee683a88c69166c6138` | Business requirements and priorities                        |
| [`MAACC_Architecture_Document.md`](MAACC_Architecture_Document.md)                                                                             | 1.0; Draft for Review      | `3697b0e7fcfa353a2d32eba2314e6bcc0d95d0db4759e1b258de789696d4254b` | Candidate architecture and existing AD-001–AD-010 decisions |
| [`MAACC_Phase_1_Decision_Baseline_v1.md`](MAACC_Phase_1_Decision_Baseline_v1.md)                                                               | Proposed                   | `b47c6d50cb71d1dcd2717c36d25a64fb8fef15f7935cabf2e6b452b1d4bce60c` | Closure of the 14 required enterprise decisions             |
| [`MAACC_Phase_1_Threat_Model_and_Control_Register_v1.md`](enterprise-readiness-evidence/MAACC_Phase_1_Threat_Model_and_Control_Register_v1.md) | Produced; approval pending | Recompute at signature                                             | Threat, data, control, owner, and residual-risk baseline    |
| [`MAACC_Enterprise_Readiness_Remediation_Plan.md`](MAACC_Enterprise_Readiness_Remediation_Plan.md)                                             | Active                     | `8fac34d950b0640db0e218eda8e9f0649f77e7977c0e03012e9ab070e9b29f80` | Remediation scope, gates, and completion ledger             |

The source hashes above identify the exact drafts reviewed. Before signatures, the release manager must recompute every hash, record the Git commit containing the complete candidate set, and restart review if any content differs.

## Reconciliation decisions required

Approval is meaningful only if the following known differences are accepted or changed explicitly:

| Topic                 | Candidate BRS/architecture wording                                                                                        | Current implementation or readiness finding                           | Proposed authoritative direction                                                                        |
| --------------------- | ------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
| Environment model     | Development, staging, and production                                                                                      | Code also defines sandbox                                             | Adopt development, sandbox, staging, and production with isolated credentials and no implicit promotion |
| Run lifecycle         | BRS names `requires_tool`; architecture also describes approval                                                           | Runtime includes `waiting_for_client` and `requires_approval`         | Adopt the versioned lifecycle in ADR-P1-007 and provide compatibility mapping before public v1          |
| Tool schema           | Requires input/output schemas without fixing a dialect                                                                    | Current implementation supports a compact schema subset               | Target JSON Schema 2020-12 for public v1; treat the compact dialect as pre-v1 compatibility only        |
| Caller context        | BRS requires caller context for client tools                                                                              | Current request primarily accepts a caller string                     | Adopt signed, minimized structured claims in ADR-P1-008 before public v1                                |
| Approval workflows    | Complex human-in-the-loop workflows are out of initial scope, while production publication approval is required elsewhere | Phase 1 implements four-eyes publication and staged high-risk changes | Treat governance approval as mandatory control, distinct from general business-workflow automation      |
| Tool-result retention | Open BRS question                                                                                                         | Current default is masked retention                                   | Adopt the classification-specific storage and retention rules in ADR-P1-004/005                         |
| Currency              | Reporting requires cost but does not name currency                                                                        | Catalog rates are USD-denominated estimates                           | Adopt USD source currency and prohibit silent conversion under ADR-P1-010                               |
| SDK support           | Initial SDK languages remain an open question                                                                             | PHP and TypeScript are supported; Python is experimental              | Adopt the support window in ADR-P1-011                                                                  |
| Production platform   | Architecture is vendor-neutral and incomplete for HA/DR                                                                   | Current repository cannot prove target services                       | Adopt the mandatory service capabilities and evidence gates in ADR-P1-012/013/014                       |

## Scope and change control

- Approval freezes the functional requirement IDs, Must/Should priority, enterprise decision package, and residual-risk conditions represented by the candidate set.
- A conflict is resolved in this order: approved ADR; approved BRS; approved architecture; versioned public contract; implementation plan. Implementation alone never overrides an approved requirement.
- Any material change requires a new document revision, a decision-log entry, impact analysis for server/SDKs/fixtures/migrations, and approval by every owner affected by the change.
- Deferred implementation remains a readiness gap. Approving a target decision does not assert that the code or production environment already satisfies it.
- Real sensitive data and enterprise production remain prohibited while an explicit NO-GO condition in the readiness plan remains.

## Approval checklist

- [ ] Every candidate artifact hash and the containing Git commit are recorded.
- [ ] Every reconciliation row has an explicit accept/change/defer decision with an accountable owner.
- [ ] The 14 ADR-P1 decisions are individually accepted or rejected; no row remains Proposed or ambiguous.
- [ ] Product confirms scope, priority, personas, and success measures.
- [ ] Architecture confirms ownership, environment, contract, platform, and release decisions.
- [ ] Security/IAM confirms identity, tenant isolation, secrets, approvals, and release controls.
- [ ] Privacy/Compliance confirms classification, storage, retention, deletion, legal hold, audit, and processors.
- [ ] SRE confirms SLO/SLI, capacity, RPO/RTO, backup/restore, monitoring, and recovery feasibility.
- [ ] Engineering confirms traceability and records all implementation gaps without converting them into accepted behavior.
- [ ] The signed decision and residual risks are linked from the Phase 1 completion ledger.

## Approval record

| Role                   | Named approver | Decision  | Date      | Signature/reference | Conditions or residual risk |
| ---------------------- | -------------- | --------- | --------- | ------------------- | --------------------------- |
| Product/Platform Owner | _Pending_      | _Pending_ | _Pending_ | _Pending_           | _Pending_                   |
| Architecture           | _Pending_      | _Pending_ | _Pending_ | _Pending_           | _Pending_                   |
| Security/IAM           | _Pending_      | _Pending_ | _Pending_ | _Pending_           | _Pending_                   |
| Privacy/Compliance     | _Pending_      | _Pending_ | _Pending_ | _Pending_           | _Pending_                   |
| Engineering Lead       | _Pending_      | _Pending_ | _Pending_ | _Pending_           | _Pending_                   |
| SRE/Operations         | _Pending_      | _Pending_ | _Pending_ | _Pending_           | _Pending_                   |

Until every required row is complete, the BRS v1 baseline remains **Proposed**, G0 remains open, and Phase 1 remains **NO-GO**.
