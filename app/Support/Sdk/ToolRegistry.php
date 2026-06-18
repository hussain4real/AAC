<?php

namespace App\Support\Sdk;

use App\Enums\AgentStatus;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Enums\SdkLanguage;
use App\Models\Agent;
use App\Models\Application;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use Illuminate\Database\Eloquent\Collection;

/**
 * The Tool Registry is MAAC's source of truth for which tool contracts apply to
 * an application and which client-side handlers that application must implement.
 *
 * It resolves the effective contract set (global, project, and agent scopes
 * collapse onto the owning application) and assembles the SDK manifest an
 * application fetches: available agents, required client-side tools, their
 * schemas/versions, the current per-environment implementation status, and
 * generated handler stubs.
 */
class ToolRegistry
{
    public function __construct(private readonly SdkStubGenerator $stubs) {}

    /**
     * The client-side tool contracts the given application must implement.
     *
     * @return Collection<int, ToolContract>
     */
    public function requiredClientTools(Application $application): Collection
    {
        return ToolContract::query()
            ->where('application_id', $application->id)
            ->where('execution_mode', ExecMode::Client)
            ->with([
                'agents' => fn ($query) => $query->orderBy('name'),
                'implementations' => fn ($query) => $query->where('application_id', $application->id),
            ])
            ->orderBy('name')
            ->get();
    }

    /**
     * The published agents an application may invoke through the runtime API.
     *
     * @return Collection<int, Agent>
     */
    public function availableAgents(Application $application): Collection
    {
        return Agent::query()
            ->whereHas('project', fn ($query) => $query->where('application_id', $application->id))
            ->where('status', AgentStatus::Published)
            ->with(['tools' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    /**
     * Build the full SDK manifest for an application in a given environment.
     *
     * @return array<string, mixed>
     */
    public function manifest(Application $application, Environment $environment): array
    {
        $tools = $this->requiredClientTools($application);

        return [
            'application' => [
                'id' => $application->slug,
                'name' => $application->name,
                'environment' => $environment->value,
            ],
            'generated_at' => now()->toIso8601String(),
            'sdk_languages' => SdkLanguage::options(),
            'agents' => $this->availableAgents($application)
                ->map(fn (Agent $agent): array => [
                    'slug' => $agent->agent_slug,
                    'name' => $agent->name,
                    'version' => $agent->version,
                    'status' => $agent->status->value,
                    'tools' => $agent->tools
                        ->where('execution_mode', ExecMode::Client)
                        ->pluck('slug')
                        ->values()
                        ->all(),
                ])
                ->all(),
            'tools' => $tools
                ->map(fn (ToolContract $tool): array => $this->toolEntry($tool, $environment))
                ->all(),
        ];
    }

    /**
     * Build a single manifest tool entry (contract metadata, current
     * implementation status for the environment, and generated stubs).
     *
     * @return array<string, mixed>
     */
    public function toolEntry(ToolContract $tool, Environment $environment): array
    {
        $implementation = $tool->implementations
            ->firstWhere('environment', $environment);

        return [
            'name' => $tool->slug,
            'version' => $tool->version,
            'description' => $tool->description,
            'execution_mode' => $tool->execution_mode->value,
            'sensitivity' => $tool->sensitivity->value,
            'requires_approval' => $tool->requires_approval,
            'timeout_seconds' => $tool->timeout_seconds,
            'max_payload_kb' => $tool->max_payload_kb,
            'input_schema' => $tool->input_schema,
            'output_schema' => $tool->output_schema,
            'schema_fingerprint' => $tool->schemaFingerprint(),
            'permission' => $this->stubs->permission($tool),
            'used_by_agents' => $tool->agents->pluck('agent_slug')->values()->all(),
            'implementation' => $this->implementationEntry($implementation),
            'stubs' => $this->stubs->forContract($tool),
        ];
    }

    /**
     * Serialize the per-environment implementation status (defaulting to
     * "requires implementation" when the application has not reported one).
     *
     * @return array<string, mixed>
     */
    private function implementationEntry(?ToolImplementation $implementation): array
    {
        if ($implementation === null) {
            return [
                'status' => ImplStatus::Required->value,
                'handler_name' => null,
                'implemented_version' => null,
                'last_validated_at' => null,
            ];
        }

        return [
            'status' => $implementation->status->value,
            'handler_name' => $implementation->handler_name,
            'implemented_version' => $implementation->implemented_version,
            'last_validated_at' => $implementation->last_validated_at?->toIso8601String(),
        ];
    }
}
