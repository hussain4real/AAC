# MAACC Laravel reference consumer

A minimal Laravel application showing how to integrate with MAACC using the
framework-agnostic [`maacc/sdk`](../../packages/maacc-sdk-php)
client. It proves an external Laravel app can complete an agent run — including a
client-side tool pause/resume — using only MAACC's public SDK/runtime APIs.

## What it demonstrates

- Binding a configured `MaaccClient` + local `ToolHandlerRegistry` as a Laravel
  singleton ([`MaaccServiceProvider`](src/MaaccServiceProvider.php)).
- Implementing a client-side tool against your **own** data layer
  ([`FetchRecordsHandler`](src/Handlers/FetchRecordsHandler.php) +
  [`CargoRepository`](src/Support/CargoRepository.php)). MAACC never sees the data,
  only the result.
- Driving a complete run from the console
  ([`RunAgentCommand`](src/Console/RunAgentCommand.php)).

## Install

In a real Laravel app you would require the SDK (this project references it via a
path repository in [`composer.json`](composer.json)):

```bash
composer require maacc/sdk:^0.2
```

Publish or copy the configuration in `config/maacc-consumer.php` and add the
credentials to your `.env`:

```dotenv
MAACC_BASE_URL=https://maacc.test
MAACC_CLIENT_ID=...        # from MAACC → Applications → Credentials
MAACC_CLIENT_SECRET=...    # shown once on generation/rotation
MAACC_AGENT_SLUG=e2e-ops-agent
MAACC_TOOL_FETCH_RECORDS=e2e-fetch-records
```

## Run

```bash
php artisan maacc:run-agent "Summarize today's vessel schedule"
```

The command syncs the local handler with MAACC's manifest, invokes the agent, and
when MAACC pauses for the `fetch-records` tool it executes `FetchRecordsHandler`
locally, submits the result, and prints the completed response.

See the [MAACC SDK Integration Guide](../../docs/MAACC_SDK_Integration_Guide.md)
for the full contract, environment variables, and troubleshooting.
