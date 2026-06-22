# MAAC plain-PHP CLI reference consumer

A reference consumer with **no framework** — just PHP and the framework-agnostic
[`milaha/maac-sdk`](../../packages/maac-sdk-php). It proves the MAAC integration
contract does not depend on Laravel: the same token exchange, manifest sync,
implementation reporting, and pause/resume run loop work from a bare PHP script.

## Run

```bash
export MAAC_BASE_URL=https://maac.test
export MAAC_CLIENT_ID=...        # from MAAC → Applications → Credentials
export MAAC_CLIENT_SECRET=...    # shown once on generation/rotation
export MAAC_AGENT_SLUG=e2e-ops-agent
export MAAC_TOOL_FETCH_RECORDS=e2e-fetch-records

reference-apps/php-cli-consumer/bin/maac-run "Summarize current port operations"
```

It prints the completed run as JSON and exits non-zero if the run did not
complete. The client-side `fetch-records` tool is implemented in
[`FetchRecordsHandler`](src/FetchRecordsHandler.php) in plain PHP.

See the [MAAC SDK Integration Guide](../../docs/MAAC_SDK_Integration_Guide.md)
for the full contract and troubleshooting.
