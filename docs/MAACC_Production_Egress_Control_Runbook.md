# MAACC Production Egress Control Runbook

## Purpose

This runbook is the infrastructure half of the MAACC outbound-request control. Application validation, full DNS classification, connection pinning, redirect revalidation, and sensitive-header stripping are necessary but do not replace a network boundary. Production outbound HTTP remains fail-closed until an operator sets `MAACC_EGRESS_INFRASTRUCTURE_ENFORCED=true` after completing and evidencing this runbook.

## Required production policy

The web and queue runtimes must share the same enforced egress boundary:

- Deny outbound traffic by default.
- Permit DNS only to the approved recursive resolver; block direct DNS and DNS-over-HTTPS to unapproved resolvers.
- Permit TCP 443 only through the controlled egress firewall or proxy. Do not permit tenant-selectable ports.
- Deny IPv4 loopback, RFC 1918, carrier-grade NAT, link-local, benchmarking, documentation, multicast, reserved, and cloud metadata ranges.
- Deny IPv6 unspecified, loopback, IPv4-mapped private/reserved, translation special-use, documentation, unique-local, link-local, multicast, and other non-global ranges.
- Deny platform metadata services by address and hostname, including `169.254.169.254`, `metadata.google.internal`, `metadata.goog`, and equivalent provider endpoints.
- Permit provider-owned fixed destinations through explicit rules. Tenant destinations must traverse the inspected/pinned application client and the controlled proxy or equivalent policy enforcement point.
- Record destination, resolved address, port, runtime identity, decision, bytes, and timestamp in centralized immutable telemetry; alert on denies, policy bypass attempts, and unusual destination volume.
- Apply the same policy to web, queue, scheduler, maintenance, and one-off command runtimes.

## Activation evidence

Before enabling the attestation variable, attach all of the following to the release record:

1. Exported firewall/proxy policy with rule identifiers and change approver.
2. Runtime identity and network-segment mapping for web, queue, and scheduler workloads.
3. Successful HTTPS probe to an approved public test endpoint from each runtime class.
4. Failed probes to loopback, RFC 1918, link-local metadata, multicast, disallowed port, IPv6 local, and an unapproved direct-DNS path.
5. Flow-log/SIEM evidence for both permitted and denied probes, including an alert delivery test.
6. Rollback procedure and a named Security/SRE owner.

Only after the evidence is reviewed may the deployment set:

```dotenv
MAACC_EGRESS_INFRASTRUCTURE_ENFORCED=true
```

Removing the variable or setting it to `false` immediately blocks all production tenant-controlled outbound destinations at the application boundary.

## Recertification

Recertify after network topology, cloud provider, proxy, DNS, queue runtime, or scheduler changes, and at least quarterly. A failed control test requires removal of the attestation variable until remediation and re-test are complete.
