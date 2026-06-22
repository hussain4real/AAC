# Changelog — `milaha/maac-sdk`

All notable changes to the PHP SDK are documented here. This project adheres to
[Semantic Versioning](https://semver.org/spec/v2.0.0.html) and
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

The SDK's MAJOR version tracks the MAAC **API contract version** it targets: a
breaking change to a MAAC SDK/runtime response shape bumps the MAJOR of both.

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
