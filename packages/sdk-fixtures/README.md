# MAACC SDK Contract Fixtures

`contract.json` is the **shared, language-agnostic contract fixture suite** every
supported MAACC SDK must pass. It is the single source of truth for the rules an
SDK has to implement identically to the server:

| Section              | What it pins                                              | Verified by |
|----------------------|-----------------------------------------------------------|-------------|
| `schema_validation`  | tool input/output payload validation (the rules + the exact error strings) | MAACC, PHP SDK, TS SDK |
| `compatibility`      | reported-implementation status (`implemented` / `outdated` / `incompatible`) | MAACC, PHP SDK, TS SDK |
| `errors`             | the controlled error envelope (`{error, message}` → code + HTTP status)     | MAACC, PHP SDK, TS SDK |
| `fingerprint`        | the schema fingerprint algorithm (sha256 of the normalised shape)           | MAACC (authoritative) |
| `version_negotiation`| the SDK-version negotiation verdict (`GET /api/v1/sdk`)                      | MAACC (authoritative) |

## It is generated, not hand-written

The file is produced from MAACC's own logic
(`App\Support\Sdk\ContractFixtures`), so it cannot drift from the server by
accident:

```bash
php artisan maacc:sdk-fixtures          # regenerate after a contract change
php artisan maacc:sdk-fixtures --check   # CI tripwire — fails if out of date
```

## The CI tripwire

`maacc:sdk-fixtures --check` runs in `composer ci:check`. If a server-side
schema/compatibility/negotiation rule or error envelope changes, the regenerated
fixtures change, and:

1. the `--check` (and `ContractFixtureTest`) fails until the fixtures are
   committed, then
2. the per-language fixture tests (`tests/Unit/Sdk/ContractFixturesTest` for PHP,
   `packages/maacc-sdk-ts/test/fixtures.test.ts` for TypeScript) fail until each
   SDK is updated to match.

That is the guarantee: **a MAACC response-shape change cannot silently break a
supported SDK client.**
