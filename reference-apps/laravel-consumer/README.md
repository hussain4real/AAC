# MAAC Laravel reference consumer

A minimal Laravel application showing how to integrate with the Milaha AI Agent
Center (MAAC) using the framework-agnostic [`milaha/maac-sdk`](../../packages/maac-sdk-php)
client. It proves an external Laravel app can complete an agent run — including a
client-side tool pause/resume — using only MAAC's public SDK/runtime APIs.

## What it demonstrates

- Binding a configured `MaacClient` + local `ToolHandlerRegistry` as a Laravel
  singleton ([`MaacServiceProvider`](src/MaacServiceProvider.php)).
- Implementing a client-side tool against your **own** data layer
  ([`FetchRecordsHandler`](src/Handlers/FetchRecordsHandler.php) +
  [`CargoRepository`](src/Support/CargoRepository.php)). MAAC never sees the data,
  only the result.
- Driving a complete run from the console
  ([`RunAgentCommand`](src/Console/RunAgentCommand.php)).

## Install

In a real Laravel app you would require the SDK (this project references it via a
path repository in [`composer.json`](composer.json)):

```bash
composer require milaha/maac-sdk
```

Publish or copy the configuration in `config/maac-consumer.php` and add the
credentials to your `.env`:

```dotenv
MAAC_BASE_URL=https://maac.test
MAAC_CLIENT_ID=...        # from MAAC → Applications → Credentials
MAAC_CLIENT_SECRET=...    # shown once on generation/rotation
MAAC_AGENT_SLUG=e2e-ops-agent
MAAC_TOOL_FETCH_RECORDS=e2e-fetch-records
```

## Run

```bash
php artisan maac:run-agent "Summarize today's vessel schedule"
```

The command syncs the local handler with MAAC's manifest, invokes the agent, and
when MAAC pauses for the `fetch-records` tool it executes `FetchRecordsHandler`
locally, submits the result, and prints the completed response.

See the [MAAC SDK Integration Guide](../../docs/MAAC_SDK_Integration_Guide.md)
for the full contract, environment variables, and troubleshooting.
