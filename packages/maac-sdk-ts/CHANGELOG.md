# Changelog — `@maac/sdk`

All notable changes to the TypeScript SDK are documented here. This project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html) and
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

The SDK's MAJOR version tracks the MAAC **API contract version** it targets: a
breaking change to a MAAC SDK/runtime response shape bumps the MAJOR of both.

## [0.0.1] — 2026-06-22

Initial release. Targets MAAC API contract **v0.0.1**.

### Added

- `SDK_VERSION` / `SDK_LANGUAGE` — reported to MAAC on every request
  (`X-Maac-Sdk-Version`) and in implementation reports (`sdk_version`).
- `MaacClient.compatibility()` — negotiates with `GET /api/v1/sdk` and returns an
  `SdkCompatibility` verdict (`compatible` / `upgrade_required` / `ahead` /
  `unknown`) so an app can detect an incompatible build before invoking anything.
  `isSdkCompatible()` is the matching helper.
- `validateSchema()`, `ToolTester` — validate a local handler's arguments and
  result against the MAAC contract schema before reporting it as implemented
  (mirrors MAAC's `ToolSchema` exactly).
- `evaluateCompatibility()`, `compareVersions()` — predict the implementation
  status MAAC will assign from a contract version + fingerprint, locally.
- Conformance to the shared contract fixture suite (`packages/sdk-fixtures`).
- `examples/simple.ts` and `examples/advanced.ts`.

### Contract baseline (v0.0.1)

- Token exchange (`client_credentials`), manifest sync, implementation reporting,
  `startRun` / `getRun` / `submitToolResult`, and the auto-resume `run()` loop.
- Typed errors: `MaacApiError`, `MissingToolHandlerError`, `RunNotResolvedError`,
  `TransportError`.
- Zero runtime dependencies (global `fetch`); runs on Node ≥ 18.

### Changed

- Version set to the initial `0.0.1` baseline.

[0.0.1]: https://example.com/maac-sdk-ts/releases/0.0.1
