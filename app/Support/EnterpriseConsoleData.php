<?php

namespace App\Support;

use App\Http\Resources\Maacc\IncidentActionResource;
use App\Http\Resources\Maacc\ModelRoutingPolicyResource;
use App\Http\Resources\Maacc\SsoConnectionResource;
use App\Http\Resources\Maacc\VaultSecretResource;
use App\Models\SsoConnection;
use App\Models\Team;
use App\Support\Runtime\Routing\ProviderHealth;

/**
 * Assembles the Phase 6G enterprise console dataset for a team — the secrets
 * vault inventory, advanced model routing policies and provider-health signals,
 * enterprise identity (SSO) connections, and the incident-response timeline — and
 * merges it into the shared `maacc` Inertia prop alongside {@see GovernanceConsoleData}.
 */
class EnterpriseConsoleData
{
    /**
     * Build the enterprise dataset for the given team.
     *
     * @return array<string, mixed>
     */
    public static function forTeam(Team $team): array
    {
        return [
            'vaultSecrets' => self::vaultSecrets($team),
            'routingPolicies' => self::routingPolicies($team),
            'providerHealth' => self::providerHealth($team),
            'incidents' => self::incidents($team),
            'ssoConnections' => self::ssoConnections($team),
        ];
    }

    /**
     * The enterprise identity (SSO) connections for the identity page.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function ssoConnections(Team $team): array
    {
        if (! request()->user()?->can('viewAny', SsoConnection::class)) {
            return [];
        }

        $connections = $team->ssoConnections()
            ->withCount('identities')
            ->orderBy('name')
            ->get();

        return SsoConnectionResource::collection($connections)->resolve();
    }

    /**
     * The break-glass / incident-response timeline for the incidents page.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function incidents(Team $team): array
    {
        $incidents = $team->incidentActions()
            ->with('actor')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get();

        return IncidentActionResource::collection($incidents)->resolve();
    }

    /**
     * The vault secret inventory (never the plaintext) for the secrets page.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function vaultSecrets(Team $team): array
    {
        $secrets = $team->vaultSecrets()
            ->with(['creator', 'llmProviders'])
            ->orderByDesc('created_at')
            ->get();

        return VaultSecretResource::collection($secrets)->resolve();
    }

    /**
     * The advanced model routing policies for the routing page.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function routingPolicies(Team $team): array
    {
        $policies = $team->modelRoutingPolicies()
            ->with(['agent', 'primaryProvider'])
            ->orderBy('name')
            ->get();

        return ModelRoutingPolicyResource::collection($policies)->resolve();
    }

    /**
     * A recent-health snapshot for each approved model, so the routing page can
     * show which providers are degraded.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function providerHealth(Team $team): array
    {
        $providers = $team->llmProviders()->get();
        $health = app(ProviderHealth::class)->forProviderIds($providers->pluck('id')->all());

        return $providers->map(fn ($provider): array => [
            'id' => $provider->id,
            'name' => $provider->name,
            'code' => $provider->code,
            'sampleSize' => $health[$provider->id]->sampleSize,
            'failureRate' => $health[$provider->id]->failureRate,
            'healthy' => $health[$provider->id]->healthy,
            'avgLatencyMs' => $health[$provider->id]->avgLatencyMs,
        ])->all();
    }
}
