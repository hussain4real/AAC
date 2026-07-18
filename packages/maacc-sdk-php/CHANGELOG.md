# Changelog — `maacc/sdk`

All notable changes to the PHP SDK are documented here. This project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html) and
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

The SDK's MAJOR version tracks the MAACC **API contract version** it targets: a
breaking change to a MAACC SDK/runtime response shape bumps the MAJOR of both.

## [1.0.0] — 2026-07-18

Targets the frozen MAACC API contract **v1.0.0**.

### Added

- Versioned compact-schema 1.0 validation with nested objects/items, enums, bounds, formats, closed-object projection, and shared fixtures.
- Signed minimized caller-context issuance, run submission, response parsing, and tool context propagation.
- Webhook endpoint verification before activation and v1 manifest status semantics.

### Changed

- `waiting_for_client` is the durable client-tool pause; `requires_tool` is a transient server decision retained only as a 1.x compatibility token.
- Pre-v1 clients are outside the supported compatibility window.

## [0.2.0] — 2026-06-23

Adds visibility of server-side tools. Still targets MAACC API contract **v0.0.1**
and is fully backward compatible.

### Added

- `ManifestAgent::$serverTools` — the tools MAACC executes itself (MAACC-hosted,
  remote HTTP, and MCP connector), each with its `name`, `execution_mode`, and
  `description`, so an application can distinguish them from the client-side
  handlers it must implement (it implements nothing for server-side tools).
- The manifest's `sdk.capabilities.tool_execution_modes` now advertises which
  execution modes are client-side versus executed by MAACC.

## [0.1.0] — 2026-06-22

Adds the long-running and interactive runtime modes. Still targets MAACC API
contract **v0.0.1** and is fully backward compatible — existing synchronous calls
are unchanged.

### Added

- `startRun(..., mode: MaaccClient::MODE_ASYNC)` to queue a long-running run for a
  worker (returned `202 queued`).
- `pollRun()` and `runAsync()` — the polling integration mode; `runAsync()` also
  services client-side tool pauses from the registry.
- `registerWebhook()` / `listWebhooks()` / `deleteWebhook()` for run-event webhook
  delivery, plus `Maacc\Sdk\Webhooks\WebhookSignature` to verify the HMAC-SHA256
  signature on the receiving side (pinned by the shared contract fixtures).
- `streamRun()` — consume a run's Server-Sent Events lifecycle.
- New `Resources\WebhookEndpoint` and `Resources\RunEvent` DTOs; `Run::isSettled()`.

## [0.0.1] — 2026-06-22

Initial release. Targets MAACC API contract **v0.0.1**.

### Added

- `MaaccClient::VERSION` — the package version, reported to MAACC on every request
  (`X-Maacc-Sdk-Version`) and in implementation reports (`sdk_version`).
- `MaaccClient::compatibility()` — negotiates with `GET /api/v1/sdk` and returns a
  `SdkCompatibility` verdict (`compatible` / `upgrade_required` / `ahead` /
  `unknown`) so an app can detect an incompatible build before invoking anything.
- `Maacc\Sdk\Testing\SchemaValidator` and `Maacc\Sdk\Testing\ToolTester` — validate
  a local handler's arguments and result against the MAACC contract schema before
  reporting it as implemented (mirrors MAACC's `ToolSchema` exactly).
- `Maacc\Sdk\Testing\Compatibility` — predict the implementation status MAACC will
  assign (`implemented` / `outdated` / `incompatible`) from a contract version +
  fingerprint, locally.
- Conformance to the shared contract fixture suite (`packages/sdk-fixtures`).
- `examples/simple.php` and `examples/advanced.php`.

### Contract baseline (v0.0.1)

- Token exchange (`client_credentials`), manifest sync, implementation reporting,
  `startRun` / `getRun` / `submitToolResult`, and the auto-resume `run()` loop.
- Typed errors: `MaaccApiException`, `MissingToolHandlerException`,
  `RunNotResolvedException`, `TransportException`.

[0.0.1]: https://example.com/maacc-sdk-php/releases/0.0.1
