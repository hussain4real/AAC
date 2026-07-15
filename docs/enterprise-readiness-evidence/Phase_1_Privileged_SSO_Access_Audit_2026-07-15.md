# Phase 1 Privileged SSO Access Audit — 15 July 2026

## Scope and decision

The new `maacc:audit-sso-access` command performs a read-only inventory of users holding global MAACC platform roles, their external SSO identities, active web sessions, local recovery factors, and access-ledger alignment. It fingerprints session IDs and external subjects and never exports session payloads, raw OIDC claims, credentials, tokens, or SSO client secrets.

The local development audit completed, but it does not satisfy the required staging/production identity and session audit. MAACC therefore remains NO-GO until Security/IAM runs and reviews the command in every real environment, revokes suspicious links/sessions, and signs the result.

## Local result

Command: `php artisan maacc:audit-sso-access --json`

Generated at: `2026-07-15T13:53:08+00:00`

| Measure | Result |
| --- | ---: |
| Privileged users | 1 |
| Privileged SSO identities | 0 |
| Active web sessions inside the configured lifetime | 0 |
| Findings | 1 High |

The first post-migration audit found three High local-demo findings: no human-held MFA factor, a persistent remember token, and an authorization role without a corresponding active governance-ledger grant. The local remediation then:

- Created the system-attributed, uncertified bootstrap grant through `PlatformAccessManager::bootstrap`, preserving the role and adding the required audit-ledger entry.
- Revoked the existing local remember token. No active database-backed web session or privileged SSO identity existed to revoke.

The subsequent read-only audit reports only:

- `privileged_local_mfa_not_confirmed`: no registered passkey or confirmed TOTP factor exists in the local database.

This is a local demo-data finding, not evidence about staging or production. It must not be silently remediated by the scanner: a named human must enroll and retain custody of the phishing-resistant factor before this account can support a break-glass exercise.

## Automated safety evidence

[`SsoAccessIntegrityAuditTest.php`](../../tests/Feature/SsoAccessIntegrityAuditTest.php) proves that the command:

- Reports privileged SSO identity, email mismatch, inactive-connection, local-MFA, remember-token, role-to-ledger, ledger-to-role, duplicate-grant, and session-coverage findings.
- Honors the configured database-session connection and table, includes active-session metadata needed for review while hashing the session identifier, and reports unsupported or unavailable session stores instead of claiming zero sessions.
- Does not emit external subjects, raw claims, session IDs, or session payloads.
- Does not delete or mutate the SSO identity or session under review.
- Returns success for a locally protected Super Admin with a governed grant and passkey.

Latest focused result: 8 tests passed, 60 assertions, 0 failures.

## Required real-environment procedure

1. Security/IAM runs `php artisan maacc:audit-sso-access --json` separately in staging and production using a controlled terminal and stores the output as Restricted evidence.
2. Review every `privileged_sso_identity` as a Critical finding. Tenant SSO must not be an authentication path for a platform administrator.
3. Review active session fingerprint, source IP, user agent, and last activity with the named account owner. Revoke suspicious or unexplained sessions through the approved session-revocation procedure.
4. Review email mismatch and inactive-connection findings, quarantine the identity link, and preserve the decision/audit record before deletion or mutation.
5. Confirm every local Super Admin/break-glass custodian has phishing-resistant local authentication and an active governed access grant.
6. Attach the before/after reports, revocation evidence, reviewer, timestamp, exceptions, and residual-risk decision to the Phase 1 gate record.

No staging/production result or Security/IAM approval is present in this repository as of this evidence date.
