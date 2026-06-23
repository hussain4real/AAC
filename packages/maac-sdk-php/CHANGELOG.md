# Changelog — `milaha/maac-sdk`

All notable changes to the PHP SDK are documented here. This project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html) and
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

The SDK's MAJOR version tracks the MAAC **API contract version** it targets: a
breaking change to a MAAC SDK/runtime response shape bumps the MAJOR of both.

## [0.2.0] — 2026-06-23

Adds visibility of server-side tools. Still targets MAAC API contract **v0.0.1**
and is fully backward compatible.

### Added

- `ManifestAgent::$serverTools` — the tools MAAC executes itself (MAAC-hosted,
  remote HTTP, and MCP connector), each with its `name`, `execution_mode`, and
  `description`, so an application can distinguish them from the client-side
  handlers it must implement (it implements nothing for server-side tools).
- The manifest's `sdk.capabilities.tool_execution_modes` now advertises which
  execution modes are client-side versus executed by MAAC.

## [0.1.0] — 2026-06-22

Adds the long-running and interactive runtime modes. Still targets MAAC API
contract **v0.0.1** and is fully backward compatible — existing synchronous calls
are unchanged.

### Added

- `startRun(..., mode: MaacClient::MODE_ASYNC)` to queue a long-running run for a
  worker (returned `202 queued`).
- `pollRun()` and `runAsync()` — the polling integration mode; `runAsync()` also
  services client-side tool pauses from the registry.
- `registerWebhook()` / `listWebhooks()` / `deleteWebhook()` for run-event webhook
  delivery, plus `Maac\Sdk\Webhooks\WebhookSignature` to verify the HMAC-SHA256
  signature on the receiving side (pinned by the shared contract fixtures).
- `streamRun()` — consume a run's Server-Sent Events lifecycle.
- New `Resources\WebhookEndpoint` and `Resources\RunEvent` DTOs; `Run::isSettled()`.

## [0.0.1] — 2026-06-22

Initial release. Targets MAAC API contract **v0.0.1**.

### Added

- `MaacClient::VERSION` — the package version, reported to MAAC on every request
  (`X-Maac-Sdk-Version`) and in implementation reports (`sdk_version`).
- `MaacClient::compatibility()` — negotiates with `GET /api/v1/sdk` and returns a
  `SdkCompatibility` verdict (`compatible` / `upgrade_required` / `ahead` /
  `unknown`) so an app can detect an incompatible build before invoking anything.
- `Maac\Sdk\Testing\SchemaValidator` and `Maac\Sdk\Testing\ToolTester` — validate
  a local handler's arguments and result against the MAAC contract schema before
  reporting it as implemented (mirrors MAAC's `ToolSchema` exactly).
- `Maac\Sdk\Testing\Compatibility` — predict the implementation status MAAC will
  assign (`implemented` / `outdated` / `incompatible`) from a contract version +
  fingerprint, locally.
- Conformance to the shared contract fixture suite (`packages/sdk-fixtures`).
- `examples/simple.php` and `examples/advanced.php`.

### Contract baseline (v0.0.1)

- Token exchange (`client_credentials`), manifest sync, implementation reporting,
  `startRun` / `getRun` / `submitToolResult`, and the auto-resume `run()` loop.
- Typed errors: `MaacApiException`, `MissingToolHandlerException`,
  `RunNotResolvedException`, `TransportException`.

[0.0.1]: https://example.com/maac-sdk-php/releases/0.0.1
