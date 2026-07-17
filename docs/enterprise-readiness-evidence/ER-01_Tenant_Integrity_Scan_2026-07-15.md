# ER-01 Tenant Association Integrity Scan — 15 July 2026

## Scope and safety

This evidence records the Phase 1 local-development scan of MAACC's persisted tenant and parent associations. The scanner is read-only: it reports mismatches and returns a non-zero exit code without repairing, deleting, disabling, or re-parenting any record.

Command:

```bash
php artisan maacc:scan-tenant-integrity --json
```

Covered associations include declared tenant/parent ownership, agent-to-project model approval, tool assignments, tool implementations, routing candidates, approval subjects, quota subjects, connectors, knowledge sources, and data sources.

## Initial result

The first local scan found two project-allowlist mismatches in the demo dataset:

1. The disabled `Compliance Assistant Agent` referenced `GPT-4o`, while its `Month-End Close Assist` project allowed only `Claude 3.7 Sonnet`.
2. The enabled `Operations tiered routing` policy used `GPT-4o Mini` as its primary candidate, while `Fleet Operations Intelligence` did not include that provider in its project allowlist.

Both records belonged to the correct tenant; the defect was an approved-parent mismatch. Runtime defense-in-depth filtered the invalid routing candidate, and the disabled agent could not execute, so neither mismatch represented an active cross-tenant exposure.

## Remediation and prevention

- The demo seeder now assigns `Compliance Assistant Agent` to the model approved for its project.
- The demo seeder now includes `GPT-4o Mini` in the operations project's model allowlist.
- Agent writes and routing-policy writes revalidate tenant, project, environment, status, verification, and allowlist eligibility inside row-locked transactions.
- Runtime routing only selects verified providers on the agent project's allowlist.
- A composite database foreign key prevents an agent from referencing a `(project_id, llm_provider_id)` pair absent from `project_llm_provider`.
- Project updates cannot remove providers still referenced by agents or routing policies.
- SDK manifest and runtime-start boundaries fail closed on mismatched persisted tool/provider relationships.

## Post-remediation result

After reseeding the corrected demo records and applying the database constraint, the scan returned:

```json
{
  "read_only": true,
  "finding_count": 0,
  "findings": []
}
```

Automated proof includes the clean demo-dataset scan, deliberately corrupted two-tenant records, direct action bypass attempts, runtime/manifest containment, and database-constraint enforcement.

## Residual operational action

This is local-development evidence, not staging or production sign-off. Security/IAM or the accountable change owner must run the same command against each real environment, preserve the JSON output in the controlled evidence store, and approve any quarantine/remediation record before credentials or runtime are re-enabled.
