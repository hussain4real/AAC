# MAAC SDK Distribution Guide

This guide describes the private, manual-first release process for making the
MAAC SDKs available to pilot application teams.

## Package Names

| Language | Package | Version | Distribution |
|----------|---------|---------|--------------|
| PHP | `maac/sdk` | `0.2.0` | Private Composer VCS package repository |
| TypeScript | `@qatar-navigation-milaha/sdk` | `0.2.0` | GitHub Packages npm registry |

The MAAC API contract remains `v0.0.1`. These package versions are client
library versions and can move independently as long as the API contract remains
backward compatible.

## Important Composer Note

GitHub Packages supports the npm registry used by
`@qatar-navigation-milaha/sdk`, but it does not provide a Composer package
registry. For the manual pilot release, PHP consumers install `maac/sdk` from a
private Composer-compatible source: either a split PHP SDK repository whose root
`composer.json` is `maac/sdk`, or a package/artifact repository such as Private
Packagist. Do not point Composer VCS at the MAAC monorepo root; Composer reads
the root `composer.json` and will not discover the nested
`packages/maac-sdk-php/composer.json` package.

The TypeScript SDK is published under the Qatar Navigation Milaha GitHub
organization scope because GitHub Packages requires the npm package scope to
match an account or organization that the publisher can write to.

## Consumer Authentication

Do not commit tokens, `.npmrc` files containing tokens, or Composer auth files.
Use machine/user tokens stored in each consuming application's secret manager or
CI secrets.

Required token capabilities:

- TypeScript package install from GitHub Packages: `read:packages` and repository
  access to the package.
- TypeScript package publish: `write:packages`, plus repository access.
- PHP package install from private GitHub VCS: repository read access.

## Installing The PHP SDK

In the consuming application's `composer.json`, add the private repository whose
root package is the tagged `maac/sdk` package:

```json
{
  "repositories": [
    {
      "type": "vcs",
      "url": "https://github.com/OWNER/maac-sdk-php.git"
    }
  ],
  "require": {
    "maac/sdk": "^0.2"
  }
}
```

Configure Composer auth outside the repository:

```bash
composer config --global --auth github-oauth.github.com "$GITHUB_TOKEN"
composer require maac/sdk:^0.2
```

For local development inside this monorepo, the reference apps can continue to
use the path repository that points at `../../packages/maac-sdk-php`.

## Installing The TypeScript SDK

Create a local or CI-provided `.npmrc` for the consuming application. Do not
commit a token value.

```ini
@qatar-navigation-milaha:registry=https://npm.pkg.github.com
//npm.pkg.github.com/:_authToken=${GITHUB_TOKEN}
```

Install the package:

```bash
npm install @qatar-navigation-milaha/sdk@^0.2
```

## Required Consumer Environment

Each consuming application still needs runtime credentials generated from the
MAAC console:

| Variable | Purpose |
|----------|---------|
| `MAAC_BASE_URL` | Base URL for the MAAC instance. |
| `MAAC_CLIENT_ID` | Application credential client id. |
| `MAAC_CLIENT_SECRET` | Application credential secret shown once on generate/rotate. |
| `MAAC_AGENT_SLUG` | Published agent slug used by the app or reference consumer. |
| Tool slug mappings | App-specific env vars that map local handlers to MAAC tool contracts. |

## Manual Release Checklist

Run these checks before publishing or tagging:

```bash
composer validate packages/maac-sdk-php/composer.json
(cd packages/maac-sdk-ts && npm run pack:dry)
npm run types:check:sdk
npm run test:sdk
composer test:reference
php artisan maac:sdk-fixtures --check
composer ci:check
php artisan test --coverage --min=100 --compact
```

Record the release evidence:

- Git SHA.
- PHP package version.
- TypeScript package version.
- MAAC API contract version.
- `packages/sdk-fixtures/contract.json` checksum.
- Local and CI gate results.

## Manual Publish Steps

1. Confirm both package metadata files report `0.2.0`.
2. Confirm the SDK READMEs and integration docs use `maac/sdk` and `@qatar-navigation-milaha/sdk`.
3. Tag the coordinated SDK release:

   ```bash
   git tag sdk-v0.2.0
   git push origin sdk-v0.2.0
   ```

4. Publish the TypeScript package manually:

   ```bash
   cd packages/maac-sdk-ts
   npm publish
   ```

5. Verify a clean PHP install from the private Composer source and a clean npm
   install from GitHub Packages in temporary consumer projects.
6. Share the integration guide, package install commands, required environment
   variables, and credential generation steps with pilot application teams.

## Consumer Smoke Checks

For PHP, create a temporary project with the split SDK VCS repository or private
Composer registry configured, install `maac/sdk:^0.2`, and verify autoloading:

```bash
php -r "require 'vendor/autoload.php'; echo Maac\\Sdk\\MaacClient::VERSION.PHP_EOL;"
```

For TypeScript, create a temporary Node project with the `.npmrc` registry
configuration, install `@qatar-navigation-milaha/sdk@^0.2`, and verify imports:

```bash
node -e "import('@qatar-navigation-milaha/sdk').then((sdk) => console.log(sdk.SDK_VERSION))"
```
