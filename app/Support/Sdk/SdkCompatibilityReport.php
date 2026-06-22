<?php

namespace App\Support\Sdk;

use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Models\Application;
use App\Models\Team;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use Illuminate\Database\Eloquent\Collection;

/**
 * Assembles the SDK compatibility dashboard dataset for a team (Phase 6C): the
 * versioned platform identity, each application's integration health (the SDK
 * client versions it reports and whether they are within the supported window),
 * and the contract *drift* feed — client-side tools whose reported
 * implementation has fallen outdated or incompatible with the current contract.
 *
 * The drift feed is the "contract changes are visible before deployment" signal:
 * it surfaces exactly which application/tool pairs need a migration before the
 * contract change is rolled out.
 */
class SdkCompatibilityReport
{
    public function __construct(private readonly SdkPlatform $platform) {}

    /**
     * Build the compatibility dataset for the given team.
     *
     * @return array<string, mixed>
     */
    public function forTeam(Team $team): array
    {
        $applications = $team->applications()
            ->with('credentials')
            ->orderBy('name')
            ->get();

        /** @var Collection<int, ToolContract> $tools */
        $tools = ToolContract::query()
            ->whereIn('application_id', $applications->modelKeys())
            ->where('execution_mode', ExecMode::Client)
            ->with('implementations')
            ->orderBy('name')
            ->get();

        return [
            'platform' => $this->platform->descriptor(),
            'applications' => $applications
                ->map(fn (Application $application): array => $this->applicationHealth($application, $tools))
                ->values()
                ->all(),
            'drift' => $this->drift($applications, $tools),
        ];
    }

    /**
     * Summarise one application's SDK integration health for its environment.
     *
     * @param  Collection<int, ToolContract>  $tools
     * @return array<string, mixed>
     */
    private function applicationHealth(Application $application, Collection $tools): array
    {
        $environment = $application->environment;
        $appTools = $tools->where('application_id', $application->id);

        $counts = ['total' => $appTools->count(), 'implemented' => 0, 'outdated' => 0, 'incompatible' => 0, 'required' => 0];
        $clients = [];

        foreach ($appTools as $tool) {
            $implementation = $this->implementationFor($tool, $environment);
            $status = $implementation !== null ? $implementation->status : ImplStatus::Required;

            $counts[$this->countKey($status)]++;

            if ($implementation !== null && $implementation->sdk_version !== null) {
                $clients[$this->clientKey($implementation)] = $implementation;
            }
        }

        $reportedClients = array_map(
            fn (ToolImplementation $implementation): array => $this->clientCompatibility($implementation),
            array_values($clients),
        );

        return [
            'id' => $application->slug,
            'name' => $application->name,
            'environment' => $environment->label(),
            'lastSyncedAt' => $application->lastSyncedAt(),
            'clients' => $reportedClients,
            'compatible' => array_reduce(
                $reportedClients,
                static fn (bool $carry, array $client): bool => $carry && $client['compatible'] === true,
                true,
            ),
            'tools' => $counts,
        ];
    }

    /**
     * Build the contract drift feed: every reported implementation that has
     * fallen outdated or incompatible with its current contract.
     *
     * @param  Collection<int, Application>  $applications
     * @param  Collection<int, ToolContract>  $tools
     * @return array<int, array<string, mixed>>
     */
    private function drift(Collection $applications, Collection $tools): array
    {
        $rows = [];

        foreach ($applications as $application) {
            $environment = $application->environment;

            foreach ($tools->where('application_id', $application->id) as $tool) {
                $implementation = $this->implementationFor($tool, $environment);

                if ($implementation === null || ! in_array($implementation->status, [ImplStatus::Outdated, ImplStatus::Incompatible], true)) {
                    continue;
                }

                $rows[] = [
                    'application' => $application->name,
                    'applicationId' => $application->slug,
                    'tool' => $tool->slug,
                    'status' => $implementation->status->value,
                    'environment' => $environment->label(),
                    'contractVersion' => $tool->version,
                    'implementedVersion' => $implementation->implemented_version,
                    'sdkVersion' => $implementation->sdk_version,
                    'handler' => $implementation->handler_name,
                ];
            }
        }

        return $rows;
    }

    /**
     * Resolve the implementation a tool has for an environment, if any.
     */
    private function implementationFor(ToolContract $tool, Environment $environment): ?ToolImplementation
    {
        return $tool->implementations
            ->first(fn (ToolImplementation $implementation): bool => $implementation->environment === $environment);
    }

    /**
     * The per-app count bucket a status falls into.
     */
    private function countKey(ImplStatus $status): string
    {
        return match ($status) {
            ImplStatus::Implemented => 'implemented',
            ImplStatus::Outdated => 'outdated',
            ImplStatus::Incompatible => 'incompatible',
            default => 'required',
        };
    }

    /**
     * A de-duplication key for a reported client (language + version pair).
     */
    private function clientKey(ToolImplementation $implementation): string
    {
        $language = $implementation->language?->value;

        return ($language ?? '').'|'.(string) $implementation->sdk_version;
    }

    /**
     * Resolve a reported SDK client's compatibility against the supported window.
     *
     * @return array<string, mixed>
     */
    private function clientCompatibility(ToolImplementation $implementation): array
    {
        $verdict = $this->platform->compatibility($implementation->sdk_version, $implementation->language?->value);

        return [
            'language' => $implementation->language?->label(),
            'version' => $implementation->sdk_version,
            'status' => $verdict['status'],
            'compatible' => $verdict['compatible'],
        ];
    }
}
