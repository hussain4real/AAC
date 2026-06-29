<?php

namespace App\Support\Sdk;

use App\Enums\ExecMode;
use App\Enums\RunMode;
use App\Enums\SdkLanguage;
use App\Enums\WebhookEventType;

/**
 * The versioned identity of MAAC's SDK/runtime integration surface (Phase 6C).
 *
 * It exposes the API contract version, the supported client-package version
 * window, the published-package registry, and active deprecations — and it
 * resolves whether a reported SDK client version is compatible with this MAAC
 * instance. This is the single source of truth behind the `GET /api/v1/sdk`
 * negotiation endpoint, the manifest's embedded `sdk` block, and the console's
 * compatibility dashboard.
 */
class SdkPlatform
{
    public const STATUS_COMPATIBLE = 'compatible';

    public const STATUS_UPGRADE_REQUIRED = 'upgrade_required';

    public const STATUS_AHEAD = 'ahead';

    public const STATUS_UNKNOWN = 'unknown';

    /**
     * The semantic version of the SDK/runtime API contract shape.
     */
    public function apiVersion(): string
    {
        return $this->string('api_version', '0.0.1');
    }

    /**
     * The oldest SDK client package version this MAAC instance still supports.
     */
    public function minimumClientVersion(): string
    {
        return $this->string('minimum_client_version', '0.0.1');
    }

    /**
     * The latest published SDK client package version.
     */
    public function currentClientVersion(): string
    {
        return $this->string('current_client_version', '0.0.1');
    }

    /**
     * The published SDK client packages, normalised into a list keyed by
     * language (name, version, registry, support status).
     *
     * @return array<int, array<string, mixed>>
     */
    public function packages(): array
    {
        $packages = config('maac.sdk.packages');
        $normalized = [];

        if (is_array($packages)) {
            foreach ($packages as $language => $package) {
                if (is_array($package)) {
                    $normalized[] = ['language' => (string) $language, ...$package];
                }
            }
        }

        return $normalized;
    }

    /**
     * The active contract/SDK deprecations and their removal windows.
     *
     * @return array<int, array<string, mixed>>
     */
    public function deprecations(): array
    {
        $deprecations = config('maac.sdk.deprecations');
        $normalized = [];

        if (is_array($deprecations)) {
            foreach ($deprecations as $deprecation) {
                if (is_array($deprecation)) {
                    $normalized[] = $deprecation;
                }
            }
        }

        return $normalized;
    }

    /**
     * The runtime/integration capabilities this MAAC instance supports, so an
     * SDK can detect — from a single descriptor fetch — whether asynchronous
     * runs, polling, streaming, and webhook delivery are available without
     * probing each surface. Derived from the enums so the advertised set never
     * drifts from what the runtime actually accepts.
     *
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        return [
            'runtime_modes' => RunMode::values(),
            'integration_modes' => ['blocking', 'polling', 'streaming', 'webhook'],
            'streaming' => true,
            'webhooks' => true,
            'webhook_events' => WebhookEventType::values(),
            // Which tool execution modes the runtime supports, split by who runs
            // them: the calling application via the SDK (client-side) versus MAAC
            // itself (hosted utilities, remote HTTP, MCP connectors,
            // knowledge-retrieval (RAG) sources, and read-only database queries).
            'tool_execution_modes' => [
                'client_side' => [ExecMode::Client->value],
                'server_side' => [ExecMode::Hosted->value, ExecMode::Http->value, ExecMode::Connector->value, ExecMode::Knowledge->value, ExecMode::Db->value],
            ],
        ];
    }

    /**
     * The canonical `sdk` descriptor embedded in API responses: the API
     * version, the supported client window, languages, packages, capabilities,
     * and deprecations.
     *
     * @return array<string, mixed>
     */
    public function descriptor(): array
    {
        return [
            'api_version' => $this->apiVersion(),
            'minimum_client_version' => $this->minimumClientVersion(),
            'current_client_version' => $this->currentClientVersion(),
            'languages' => SdkLanguage::options(),
            'packages' => $this->packages(),
            'capabilities' => $this->capabilities(),
            'deprecations' => $this->deprecations(),
        ];
    }

    /**
     * Resolve whether a reported SDK client version is compatible with this
     * MAAC instance: compatible, requires an upgrade, is ahead of the server,
     * or could not be determined.
     *
     * @return array{client_version: string|null, language: string|null, api_version: string, minimum_client_version: string, current_client_version: string, status: string, compatible: bool, upgrade_required: bool}
     */
    public function compatibility(?string $clientVersion, ?string $language = null): array
    {
        return self::resolveCompatibility(
            $clientVersion,
            $this->minimumClientVersion(),
            $this->currentClientVersion(),
            $this->apiVersion(),
            $language,
        );
    }

    /**
     * The pure version-negotiation rule: a client below the minimum requires an
     * upgrade, one above the current is ahead (but still served), one in the
     * window is compatible, and an unreported one is unknown (not blocked).
     * Shared by {@see self::compatibility()} and the SDK contract fixtures so the
     * verdict is identical everywhere.
     *
     * @return array{client_version: string|null, language: string|null, api_version: string, minimum_client_version: string, current_client_version: string, status: string, compatible: bool, upgrade_required: bool}
     */
    public static function resolveCompatibility(
        ?string $clientVersion,
        string $minimum,
        string $current,
        string $apiVersion,
        ?string $language = null,
    ): array {
        $version = self::trimToNull($clientVersion);

        if ($version === null) {
            $status = self::STATUS_UNKNOWN;
            $compatible = true;
        } elseif (version_compare($version, $minimum, '<')) {
            $status = self::STATUS_UPGRADE_REQUIRED;
            $compatible = false;
        } elseif (version_compare($version, $current, '>')) {
            $status = self::STATUS_AHEAD;
            $compatible = true;
        } else {
            $status = self::STATUS_COMPATIBLE;
            $compatible = true;
        }

        return [
            'client_version' => $version,
            'language' => self::trimToNull($language),
            'api_version' => $apiVersion,
            'minimum_client_version' => $minimum,
            'current_client_version' => $current,
            'status' => $status,
            'compatible' => $compatible,
            'upgrade_required' => $status === self::STATUS_UPGRADE_REQUIRED,
        ];
    }

    /**
     * Read a string config value under the `maac.sdk` namespace.
     */
    private function string(string $key, string $default): string
    {
        $value = config("maac.sdk.{$key}");

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * Trim a version/language string, returning null when it is empty.
     */
    private static function trimToNull(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        return $value === null || $value === '' ? null : $value;
    }
}
