# Phase 1 Repository Exposure Review — 15 July 2026

## Decision

This is a read-only engineering inventory, not the repository owner's visibility decision and not a credential-rotation attestation. The repository remains public. No open GitHub secret-scanning alert or high-confidence production credential was identified by the checks below, but absence of an alert does not prove that previously exposed material was never valid or that required rotations occurred.

## Current GitHub controls

Read-only GitHub API inventory for `hussain4real/AAC`:

| Control                           | Current state                                                                                                         | Phase 1 assessment                                                                             |
| --------------------------------- | --------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- |
| Visibility                        | Public                                                                                                                | Owner approval or change-to-private decision remains required                                  |
| Default branch                    | `main`                                                                                                                | In scope of active ruleset `pass`                                                              |
| Legacy branch-protection endpoint | Returns “Branch not protected”                                                                                        | Not authoritative because a repository ruleset governs the branch                              |
| Repository ruleset                | Active for `refs/heads/main`; blocks deletion/non-fast-forward; requires pull requests and `ci (8.4)` plus `ci (8.5)` | Required contexts are preserved by the pending hardened workflow; hosted proof remains pending |
| Required review count             | 0; no code-owner, last-push, or thread-resolution requirement                                                         | Insufficient for the proposed enterprise release policy; Repository Owner action required      |
| Strict up-to-date checks          | Disabled                                                                                                              | Residual merge-staleness risk; owner decision required                                         |
| Deployment environments           | 0                                                                                                                     | No protected production approval surface; remains a Phase 4 and G5 gap                         |
| GitHub secret scanning            | Enabled                                                                                                               | Useful detection control                                                                       |
| Push protection                   | Enabled                                                                                                               | Useful prevention control                                                                      |
| Non-provider patterns             | Disabled                                                                                                              | Coverage gap                                                                                   |
| Validity checks                   | Disabled                                                                                                              | Alert-verification gap                                                                         |
| Open secret-scanning alerts       | 0                                                                                                                     | No current GitHub alert; not a rotation record                                                 |
| Actions secret inventory          | Four names: `DEPLOY_HOST`, `DEPLOY_KNOWN_HOSTS`, `DEPLOY_SSH_KEY`, `DEPLOY_USER`                                      | Values were not read or exported; owner must confirm provenance, least privilege, and rotation |

## Local Git-history checks

The scan covered repository branches, remote-tracking branches, and tags while deliberately excluding Codex's internal turn-diff refs. It reported only commit IDs and filenames—never matching values.

Checks performed:

1. Sensitive filename inventory for tracked `.env`, PEM/private-key/container, credential JSON, and common SSH key names.
2. Git diff-history search for high-confidence private-key, common cloud access-key, GitHub token, OpenAI-style key, Slack token, and webhook-secret shapes.
3. Assignment-pattern search for application, Passport, OIDC, deployment, AWS, database URL, and database password variables with non-placeholder-looking values.
4. GitHub open secret-alert inventory and security-analysis configuration review.

Results:

- No sensitive filename was present in repository branches, remotes, or tags.
- No high-confidence private key or provider-token pattern was found in repository history.
- The assignment-pattern search matched only the Laravel best-practice skill's `AWS_SECRET_ACCESS_KEY` configuration example in commits `9436cb6` and `708193f`; inspection of the variable name confirmed these were documentation examples, not a discovered credential.
- A newly created local OIDC test PEM keypair was detected before commit. It was removed and replaced with a process-scoped ephemeral 2048-bit RSA keypair generated in memory by `OidcTestProvider`; focused OIDC/SSO tests pass without PEM fixtures.

## Limitations and required owner actions

- No dedicated local history scanner such as Gitleaks or TruffleHog is installed; GitHub secret scanning is enabled but non-provider patterns and validity checks are disabled.
- The review cannot determine whether an old value that does not match the heuristics was ever valid, copied to an artifact/fork/log, or rotated.
- Secret values were intentionally not retrieved, so age, scope, reuse, access history, and revocation cannot be attested.
- Public visibility may be intentional, but only the Repository Owner and Security can accept it.

Before closing the Phase 1 repository item, Repository Owner and Security must:

1. Record an explicit public/private decision and rationale.
2. Enable or deliberately reject expanded pattern/validity scanning with a documented residual-risk decision.
3. Run an approved full-history and artifact/fork inventory using the organization's scanner.
4. Review and rotate deployment, application, signing, Passport, session/app, webhook, SSO, provider, database, and external-service credentials where exposure cannot be disproved.
5. Record old-material revocation, rotation timestamps, owners, exceptions, and incident linkage.
6. Strengthen the `main` ruleset to require at least one independent approval, current-branch checks, review-thread resolution, and the final hosted enterprise contexts.
7. Create a protected production environment before enterprise deployment and preserve its approval evidence.

## Owner decision record

| Role             | Named owner | Decision  | Date      | Evidence/reference | Residual risk |
| ---------------- | ----------- | --------- | --------- | ------------------ | ------------- |
| Repository Owner | _Pending_   | _Pending_ | _Pending_ | _Pending_          | _Pending_     |
| Security         | _Pending_   | _Pending_ | _Pending_ | _Pending_          | _Pending_     |
| Release Manager  | _Pending_   | _Pending_ | _Pending_ | _Pending_          | _Pending_     |

Until these rows and actions are complete, this review is **engineering evidence only**, the repository-visibility/rotation checklist item remains open, and enterprise release remains **NO-GO**.
