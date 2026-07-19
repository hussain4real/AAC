<?php

namespace App\Support\Governance;

use App\Enums\AgentStatus;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\AppStatus;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Enums\ProjectStatus;
use App\Enums\Sensitivity;
use App\Models\Agent;
use App\Models\Application;
use App\Models\ApprovalRequest;
use App\Models\DataSource;
use App\Models\GovernanceSetting;
use App\Models\KnowledgeSource;
use App\Models\LlmProvider;
use App\Models\McpConnector;
use App\Models\ToolContract;
use App\Support\Evaluation\EvaluationGate;

/**
 * Authoritative readiness decision for every agent publication and execution
 * path. Callers may relax publication/evaluation requirements only for a
 * candidate evaluation; tenant, environment, dependency, implementation, and
 * immutable-configuration checks remain shared.
 */
class AgentReadinessGate
{
    public function __construct(
        private readonly TenantRelationshipGuard $relationships,
        private readonly EvaluationGate $evaluations,
    ) {}

    /**
     * List the reasons an agent is not ready for the requested boundary.
     *
     * @return list<string>
     */
    public function blockers(
        Agent $agent,
        Application $application,
        Environment $environment,
        bool $requirePublished = true,
        bool $requireEvaluations = true,
        bool $requireImmutableVersion = true,
    ): array {
        $agent->load([
            'project.application',
            'llmProvider.vaultSecret',
            'tools.implementations',
            'tools.mcpConnector',
            'tools.dataSource',
            'tools.knowledgeSource',
            'routingPolicy',
            'currentVersion',
        ]);

        $blockers = [];

        if (! $this->relationships->runtimeAgentRelationshipsAreValid($agent, $application, $environment)) {
            $blockers[] = 'The agent has invalid tenant, project, model, routing, or dependency ownership.';
        }

        if ($application->status !== AppStatus::Active) {
            $blockers[] = 'The application is suspended or archived.';
        }

        if ($agent->project->status !== ProjectStatus::Active) {
            $blockers[] = 'The project is archived.';
        }

        if ($agent->project->environment !== $environment || $application->environment !== $environment) {
            $blockers[] = 'The agent is not configured for the requested environment.';
        }

        if ($requirePublished && $agent->status !== AgentStatus::Published) {
            $blockers[] = 'The agent is not published.';
        }

        if (! $requirePublished && $agent->status === AgentStatus::Disabled) {
            $blockers[] = 'A disabled agent cannot enter publication or evaluation.';
        }

        if (! $this->hasEligibleProvider($agent, $environment)) {
            $blockers[] = "No project-approved model is verified, credentialed, and available for {$environment->label()}.";
        }

        foreach ($agent->tools as $tool) {
            array_push($blockers, ...$this->toolBlockers($tool, $application, $environment));
        }

        if ($requireEvaluations) {
            array_push($blockers, ...$this->evaluations->blockers($agent));
        }

        if ($requireImmutableVersion && $agent->currentVersion === null) {
            $blockers[] = 'The agent has no approved immutable version.';
        }

        if ($requireImmutableVersion && $agent->currentVersion !== null) {
            $approvedHash = $agent->currentVersion->settings['configuration_hash'] ?? null;

            if (! is_string($approvedHash) || ! hash_equals($approvedHash, $this->configurationHash($agent))) {
                $blockers[] = 'The published configuration has changed since approval.';
            }
        }

        return array_values(array_unique($blockers));
    }

    /**
     * Whether the agent has no blockers for the requested boundary.
     */
    public function isReady(
        Agent $agent,
        Application $application,
        Environment $environment,
        bool $requirePublished = true,
        bool $requireEvaluations = true,
        bool $requireImmutableVersion = true,
    ): bool {
        return $this->blockers(
            $agent,
            $application,
            $environment,
            $requirePublished,
            $requireEvaluations,
            $requireImmutableVersion,
        ) === [];
    }

    /**
     * A deterministic fingerprint of every execution-relevant agent setting.
     */
    public function configurationHash(Agent $agent): string
    {
        return hash('sha256', (string) json_encode($this->executionSnapshot($agent), JSON_THROW_ON_ERROR));
    }

    /**
     * Build the complete, secret-free execution snapshot persisted at publish
     * and run creation time. This is the rollback/reproduction source of truth;
     * the hash is only its integrity-friendly identifier.
     *
     * @return array<string, mixed>
     */
    public function executionSnapshot(Agent $agent): array
    {
        $agent->load([
            'project.application',
            'llmProvider.vaultSecret',
            'tools.implementations',
            'tools.mcpConnector',
            'tools.dataSource',
            'tools.knowledgeSource',
            'routingPolicy',
        ]);

        $tools = $agent->tools->sortBy('id')->map(function (ToolContract $tool): array {
            return [
                'id' => $tool->id,
                'slug' => $tool->slug,
                'name' => $tool->name,
                'version' => $tool->version,
                'status' => $tool->status,
                'scope' => $tool->scope->value,
                'execution_mode' => $tool->execution_mode->value,
                'sensitivity' => $tool->sensitivity->value,
                'requires_approval' => $tool->requires_approval,
                'timeout_seconds' => $tool->timeout_seconds,
                'max_payload_kb' => $tool->max_payload_kb,
                'input_schema' => $tool->input_schema,
                'output_schema' => $tool->output_schema,
                'schema_fingerprint' => $tool->schemaFingerprint(),
                'http_config' => $tool->http_config,
                'mcp_tool_name' => $tool->mcp_tool_name,
                'knowledge_config' => $tool->knowledge_config,
                'db_config' => $tool->db_config,
                'redaction' => $tool->redaction,
                'connector' => $this->dependencyFingerprint($tool->mcpConnector),
                'data_source' => $this->dependencyFingerprint($tool->dataSource),
                'knowledge_source' => $this->dependencyFingerprint($tool->knowledgeSource),
                'implementations' => $this->implementationFingerprints($tool),
            ];
        })->values()->all();

        $routing = $agent->routingPolicy;
        $governance = GovernanceSetting::forTeam($agent->project->application->team);

        return [
            'snapshot_version' => 1,
            'runtime_policy_version' => (string) config('maacc.runtime.policy_version', '1.0.0'),
            'agent' => [
                'id' => $agent->id,
                'prompt' => $agent->system_prompt,
                'model_id' => $agent->llm_provider_id,
                'temperature' => $agent->temperature,
                'max_tokens' => $agent->max_tokens,
                'sensitivity' => $agent->sensitivity->value,
                'requires_runtime_approval' => $agent->requires_runtime_approval,
            ],
            'application' => [
                'id' => $agent->project->application_id,
                'environment' => $agent->project->application->environment->value,
                'status' => $agent->project->application->status->value,
            ],
            'project' => [
                'id' => $agent->project_id,
                'environment' => $agent->project->environment->value,
                'status' => $agent->project->status->value,
            ],
            'provider' => [
                'id' => $agent->llmProvider->id,
                'code' => $agent->llmProvider->code,
                'status' => $agent->llmProvider->status->value,
                'environments' => $agent->llmProvider->environments,
                'vault_secret_id' => $agent->llmProvider->vault_secret_id,
                'vault_secret_updated_at' => $agent->llmProvider->vaultSecret?->updated_at?->toJSON(),
                'platform_owned' => $agent->llmProvider->platform_owned,
                'verified_at' => $agent->llmProvider->verified_at?->toJSON(),
            ],
            'routing' => $routing === null ? null : [
                'id' => $routing->id,
                'name' => $routing->name,
                'strategy' => $routing->strategy->value,
                'primary_provider_id' => $routing->primary_provider_id,
                'fallback_provider_ids' => $routing->fallback_provider_ids,
                'max_cost_per_1k' => $routing->max_cost_per_1k,
                'max_latency_ms' => $routing->max_latency_ms,
                'enabled' => $routing->enabled,
            ],
            'governance_updated_at' => $governance->updated_at?->toJSON(),
            'tools' => $tools,
        ];
    }

    /**
     * Whether an agent-publication approval still matches current configuration.
     */
    public function approvalIsCurrent(ApprovalRequest $request): bool
    {
        return $request->type !== ApprovalType::AgentPublication
            || ($request->subject instanceof Agent
                && is_string($request->subject_version_hash)
                && hash_equals($request->subject_version_hash, $this->configurationHash($request->subject)));
    }

    /**
     * List blockers contributed by one assigned tool and its dependencies.
     *
     * @return list<string>
     */
    private function toolBlockers(ToolContract $tool, Application $application, Environment $environment): array
    {
        $blockers = [];

        if ($tool->status !== 'Active') {
            $blockers[] = "Tool {$tool->name} is inactive.";
        }

        if ($tool->requires_approval && ApprovalRequest::query()
            ->where('team_id', $application->team_id)
            ->where('type', ApprovalType::ToolContract)
            ->where('status', ApprovalStatus::Pending)
            ->where('subject_type', $tool->getMorphClass())
            ->where('subject_id', $tool->getKey())
            ->exists()) {
            $blockers[] = "Tool {$tool->name} is still awaiting approval.";
        }

        if ($tool->execution_mode === ExecMode::Client) {
            $implemented = $tool->implementations->contains(fn ($implementation): bool => $implementation->application_id === $application->id
                && $implementation->environment === $environment
                && $implementation->status === ImplStatus::Implemented
                && $implementation->implemented_version === $tool->version
                && $implementation->schema_fingerprint === $tool->schemaFingerprint());

            if (! $implemented) {
                $blockers[] = "Tool {$tool->name} has no implemented handler in {$environment->label()}.";
            }
        }

        if ($tool->execution_mode === ExecMode::Connector
            && (! $tool->mcpConnector instanceof McpConnector || ! $tool->mcpConnector->isAvailableIn($environment->value))) {
            $blockers[] = "Tool {$tool->name} uses an MCP connector that is disabled or unavailable in {$environment->label()}.";
        }

        if ($tool->execution_mode === ExecMode::Db
            && (! $tool->dataSource instanceof DataSource || ! $tool->dataSource->isAvailableIn($environment->value))) {
            $blockers[] = "Tool {$tool->name} uses a data source that is not approved or unavailable in {$environment->label()}.";
        }

        if ($tool->execution_mode === ExecMode::Knowledge
            && (! $tool->knowledgeSource instanceof KnowledgeSource || ! $tool->knowledgeSource->isAvailableIn($environment->value))) {
            $blockers[] = "Tool {$tool->name} has an unavailable knowledge source.";
        }

        return $blockers;
    }

    /**
     * Reduce a backing resource to the fields that affect execution eligibility.
     *
     * @return array<string, mixed>|null
     */
    private function dependencyFingerprint(McpConnector|DataSource|KnowledgeSource|null $dependency): ?array
    {
        if ($dependency === null) {
            return null;
        }

        return [
            'id' => $dependency->id,
            'status' => $dependency->status->value,
            'environments' => $dependency->environments,
            'updated_at' => $dependency->updated_at?->toJSON(),
        ];
    }

    /**
     * Reduce implementations to execution-relevant fields in stable order.
     *
     * @return list<array<string, mixed>>
     */
    private function implementationFingerprints(ToolContract $tool): array
    {
        $fingerprints = [];

        foreach ($tool->implementations->sortBy('id') as $implementation) {
            $fingerprints[] = [
                'application_id' => $implementation->application_id,
                'environment' => $implementation->environment->value,
                'status' => $implementation->status->value,
                'implemented_version' => $implementation->implemented_version,
                'schema_fingerprint' => $implementation->schema_fingerprint,
            ];
        }

        return $fingerprints;
    }

    /**
     * Confirm the default model or an enabled routing candidate can execute.
     */
    private function hasEligibleProvider(Agent $agent, Environment $environment): bool
    {
        $candidateIds = $agent->routingPolicy !== null && $agent->routingPolicy->enabled
            ? $agent->routingPolicy->candidateProviderIds()
            : [$agent->llm_provider_id];

        $requiredSensitivity = $agent->tools->reduce(
            fn (Sensitivity $carry, ToolContract $tool): Sensitivity => $tool->sensitivity->level() > $carry->level()
                ? $tool->sensitivity
                : $carry,
            $agent->sensitivity,
        );

        return $agent->project->llmProviders()
            ->where('llm_providers.team_id', $agent->project->application->team_id)
            ->whereIn('llm_providers.id', $candidateIds)
            ->with('vaultSecret')
            ->get()
            ->contains(fn (LlmProvider $provider): bool => $provider->isAvailableIn($environment->value)
                && $provider->isVerified()
                && $provider->sensitivity->isAtLeast($requiredSensitivity)
                && ($provider->platform_owned || $provider->vault_secret_id !== null));
    }
}
