# MAACC plain-PHP CLI reference consumer

A reference consumer with **no framework** — just PHP and the framework-agnostic
[`maacc/sdk`](../../packages/maacc-sdk-php). It proves the MAACC integration
contract does not depend on Laravel: the same token exchange, manifest sync,
implementation reporting, and pause/resume run loop work from a bare PHP script.

## Run

```bash
export MAACC_BASE_URL=https://maacc.test
export MAACC_CLIENT_ID=...        # from MAACC → Applications → Credentials
export MAACC_CLIENT_SECRET=...    # shown once on generation/rotation
export MAACC_AGENT_SLUG=e2e-ops-agent
export MAACC_TOOL_FETCH_RECORDS=e2e-fetch-records

reference-apps/php-cli-consumer/bin/maacc-run "Summarize current port operations"
```

It prints the completed run as JSON and exits non-zero if the run did not
complete. The client-side `fetch-records` tool is implemented in
[`FetchRecordsHandler`](src/FetchRecordsHandler.php) in plain PHP.

See the [MAACC SDK Integration Guide](../../docs/MAACC_SDK_Integration_Guide.md)
for the full contract and troubleshooting.
