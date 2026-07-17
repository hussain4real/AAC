<?php

namespace App\Support\Governance;

use App\Enums\AppStatus;
use App\Enums\Environment;
use App\Enums\LlmStatus;
use App\Enums\ProjectStatus;
use App\Enums\QuotaScope;
use App\Enums\ToolScope;
use App\Models\Agent;
use App\Models\Application;
use App\Models\ApprovalRequest;
use App\Models\DataSource;
use App\Models\EvaluationDataset;
use App\Models\KnowledgeSource;
use App\Models\LlmProvider;
use App\Models\McpConnector;
use App\Models\ModelRoutingPolicy;
use App\Models\PlatformAccessGrant;
use App\Models\Project;
use App\Models\QuotaLimit;
use App\Models\Team;
use App\Models\ToolContract;
use App\Models\WebhookEndpoint;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class TenantRelationshipGuard
{
    /**
     * Lock and validate every relationship used to create a project.
     *
     * @param  array<string, mixed>  $data
     */
    public function assertProjectRelationships(Team $team, array $data, ?Project $project = null): Application
    {
        $application = $this->applicationForTeam(
            $team,
            (string) ($project->application_id ?? $data['application_id'] ?? ''),
        );

        if ($application->status !== AppStatus::Active) {
            $this->fail('application_id', 'The selected application is not active.');
        }

        $environment = $data['environment'] ?? $project?->environment?->value;

        if (array_key_exists('llm_provider_ids', $data)) {
            $providerIds = $this->uniqueIds($data['llm_provider_ids']);

            if ($project instanceof Project) {
                $requiredProviderIds = Agent::query()
                    ->where('project_id', $project->id)
                    ->lockForUpdate()
                    ->pluck('llm_provider_id')
                    ->merge(ModelRoutingPolicy::query()
                        ->whereHas('agent', fn ($query) => $query->where('project_id', $project->id))
                        ->with('agent')
                        ->lockForUpdate()
                        ->get()
                        ->flatMap(fn (ModelRoutingPolicy $policy): array => $policy->candidateProviderIds()))
                    ->unique()
                    ->values();

                if ($requiredProviderIds->diff($providerIds)->isNotEmpty()) {
                    $this->fail(
                        'llm_provider_ids',
                        'Models assigned to agents or routing policies cannot be removed from the project.',
                    );
                }
            }

            foreach ($providerIds as $index => $providerId) {
                $provider = LlmProvider::query()
                    ->whereKey($providerId)
                    ->where('team_id', $team->id)
                    ->lockForUpdate()
                    ->first();

                if (! $provider instanceof LlmProvider
                    || $provider->status !== LlmStatus::Approved
                    || ! $provider->isVerified()
                    || ! in_array($environment, $provider->environments, true)) {
                    $this->fail("llm_provider_ids.{$index}", 'The selected model is not an approved model for this tenant and environment.');
                }
            }
        }

        return $application;
    }

    /**
     * Validate the model and tool relationships used by an agent write.
     *
     * @param  array<string, mixed>  $data
     */
    public function assertAgentRelationships(Project $project, array $data): Project
    {
        $project = $this->projectForWrite($project);
        $application = $this->applicationForTeam($project->application->team, $project->application_id);
        $project->setRelation('application', $application);

        if ($project->status !== ProjectStatus::Active || $project->application->status !== AppStatus::Active) {
            $this->fail('project_id', 'The selected project and application must be active.');
        }

        if (array_key_exists('llm_provider_id', $data)) {
            $provider = $project->llmProviders()
                ->whereKey((string) $data['llm_provider_id'])
                ->where('llm_providers.team_id', $project->application->team_id)
                ->lockForUpdate()
                ->first();

            if (! $provider instanceof LlmProvider
                || $provider->status !== LlmStatus::Approved
                || ! $provider->isVerified()
                || ! in_array($project->environment->value, $provider->environments, true)) {
                $this->fail('llm_provider_id', 'The selected model is not approved for this project and environment.');
            }
        }

        if (array_key_exists('tool_ids', $data)) {
            $this->assertAgentToolsForProject($project, $data['tool_ids']);
        }

        return $project;
    }

    /**
     * Validate a tool contract's application and backing resources.
     *
     * @param  array<string, mixed>  $data
     */
    public function assertToolContractRelationships(Team $team, array $data, ?ToolContract $existing = null): void
    {
        $applicationId = $this->effectiveAttribute($data, 'application_id', $existing?->application_id);
        $application = null;

        if (is_string($applicationId) && $applicationId !== '') {
            $application = $this->applicationForTeam($team, $applicationId);

            if ($application->status !== AppStatus::Active) {
                $this->fail('application_id', 'The selected application is not active.');
            }
        }

        $scope = ToolScope::tryFrom((string) $this->effectiveAttribute($data, 'scope', $existing?->scope?->value));

        if ($scope === ToolScope::Global && $application instanceof Application) {
            $this->fail('application_id', 'A global tool cannot be owned by a single application.');
        }

        $this->assertOwnedBackingResource(
            McpConnector::class,
            'mcp_connector_id',
            $this->effectiveAttribute($data, 'mcp_connector_id', $existing?->mcp_connector_id),
            $team,
            $application,
        );
        $this->assertOwnedBackingResource(
            KnowledgeSource::class,
            'knowledge_source_id',
            $this->effectiveAttribute($data, 'knowledge_source_id', $existing?->knowledge_source_id),
            $team,
            $application,
        );
        $this->assertOwnedBackingResource(
            DataSource::class,
            'data_source_id',
            $this->effectiveAttribute($data, 'data_source_id', $existing?->data_source_id),
            $team,
            $application,
        );
    }

    /**
     * Revalidate tool assignment eligibility immediately before persistence.
     *
     * @param  iterable<int, string>  $toolIds
     */
    public function assertAgentTools(Agent $agent, iterable $toolIds): Agent
    {
        $agent = $this->agentForWrite($agent);
        $this->assertAgentToolsForProject($agent->project, $toolIds);

        return $agent;
    }

    /**
     * Validate a routing policy's agent and every effective provider candidate.
     *
     * @param  array<string, mixed>  $data
     */
    public function assertRoutingPolicyRelationships(
        Team $team,
        Agent $agent,
        array $data,
        ?ModelRoutingPolicy $policy = null,
    ): Agent {
        $agent = $this->agentForWrite($agent);
        $project = $agent->project;

        if ((string) $project->application->team_id !== (string) $team->id
            || ($policy instanceof ModelRoutingPolicy
                && ((string) $policy->team_id !== (string) $team->id
                    || (string) $policy->agent_id !== (string) $agent->id))) {
            $this->fail('agent_id', 'The selected routing policy does not belong to the current tenant and agent.');
        }

        if ($project->status !== ProjectStatus::Active || $project->application->status !== AppStatus::Active) {
            $this->fail('agent_id', 'Routing policies require an active project and application.');
        }

        $primaryProviderId = $this->effectiveAttribute(
            $data,
            'primary_provider_id',
            $policy?->primary_provider_id,
        );
        $fallbackProviderIds = $this->effectiveAttribute(
            $data,
            'fallback_provider_ids',
            $policy->fallback_provider_ids ?? [],
        );
        $candidateIds = $this->uniqueIds([
            is_string($primaryProviderId) && $primaryProviderId !== ''
                ? $primaryProviderId
                : $agent->llm_provider_id,
            ...(is_iterable($fallbackProviderIds) ? $fallbackProviderIds : []),
        ]);

        foreach ($candidateIds as $index => $providerId) {
            if (! $this->providerIsEligibleForProject($project, $providerId, lockForUpdate: true)) {
                $this->fail(
                    $index === 0 ? 'primary_provider_id' : 'fallback_provider_ids.'.($index - 1),
                    'The selected model is not approved for this project and environment.',
                );
            }
        }

        return $agent;
    }

    /**
     * Fail closed when imported or legacy relationships reach a runtime boundary.
     */
    public function runtimeAgentRelationshipsAreValid(
        Agent $agent,
        Application $application,
        Environment $environment,
    ): bool {
        $agent->loadMissing(['project.application', 'llmProvider', 'tools']);
        $project = $agent->project;

        if ((string) $project->application_id !== (string) $application->id
            || (string) $project->application->team_id !== (string) $application->team_id
            || $application->status !== AppStatus::Active
            || $project->status !== ProjectStatus::Active
            || ! $this->providerBelongsToProject($project, $agent->llm_provider_id)) {
            return false;
        }

        return $agent->tools->every(
            fn (ToolContract $tool): bool => $this->runtimeToolRelationshipsAreValid($tool, $project),
        );
    }

    /**
     * Determine whether a persisted tool assignment is safe for a project.
     */
    public function toolIsEligibleForProject(ToolContract $tool, Project $project): bool
    {
        $project->loadMissing('application');

        if ((string) $tool->team_id !== (string) $project->application->team_id
            || $tool->status !== 'Active'
            || ($tool->application_id !== null && $tool->application_id !== $project->application_id)
            || ($tool->scope === ToolScope::Global && $tool->application_id !== null)) {
            return false;
        }

        return $this->runtimeBackingResourceIsEligible(McpConnector::class, $tool->mcp_connector_id, $project)
            && $this->runtimeBackingResourceIsEligible(KnowledgeSource::class, $tool->knowledge_source_id, $project)
            && $this->runtimeBackingResourceIsEligible(DataSource::class, $tool->data_source_id, $project);
    }

    /**
     * Runtime ownership check. Lifecycle availability remains the executor's
     * responsibility so it can return the established controlled failure code.
     */
    private function runtimeToolRelationshipsAreValid(ToolContract $tool, Project $project): bool
    {
        $project->loadMissing('application');

        if ((string) $tool->team_id !== (string) $project->application->team_id
            || ($tool->application_id !== null && $tool->application_id !== $project->application_id)
            || ($tool->scope === ToolScope::Global && $tool->application_id !== null)) {
            return false;
        }

        return $this->runtimeBackingResourceBelongsToProject(McpConnector::class, $tool->mcp_connector_id, $project)
            && $this->runtimeBackingResourceBelongsToProject(KnowledgeSource::class, $tool->knowledge_source_id, $project)
            && $this->runtimeBackingResourceBelongsToProject(DataSource::class, $tool->data_source_id, $project);
    }

    /**
     * Lock and reload a project before relationship-sensitive writes.
     */
    public function projectForWrite(Project $project): Project
    {
        return Project::query()
            ->with('application.team')
            ->whereKey($project->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Lock and reload an agent before relationship-sensitive writes.
     */
    public function agentForWrite(Agent $agent): Agent
    {
        return Agent::query()
            ->with('project.application.team')
            ->whereKey($agent->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Lock and reload a tool contract before relationship-sensitive writes.
     */
    public function toolContractForWrite(ToolContract $toolContract): ToolContract
    {
        return ToolContract::query()
            ->with('team')
            ->whereKey($toolContract->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Lock and reload a quota before relationship-sensitive writes.
     */
    public function quotaLimitForWrite(QuotaLimit $quotaLimit): QuotaLimit
    {
        return QuotaLimit::query()
            ->with('team')
            ->whereKey($quotaLimit->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    /**
     * Prove a quota's typed subject belongs to the quota-owning team.
     */
    public function assertQuotaSubject(Team $team, QuotaScope $scope, ?string $subjectId): void
    {
        if ($scope === QuotaScope::Platform) {
            if ($subjectId !== null && $subjectId !== '') {
                $this->fail('subject_id', 'A platform quota cannot target a record subject.');
            }

            return;
        }

        if ($subjectId === null || $subjectId === '') {
            $this->fail('subject_id', 'The selected quota scope requires a subject.');
        }

        if (! $this->quotaSubjectBelongsToTeam($team, $scope, $subjectId, lockForUpdate: true)) {
            $this->fail('subject_id', 'The selected quota subject does not belong to the current tenant.');
        }
    }

    /**
     * Determine whether a route-bound model is owned by the selected team.
     */
    public function modelBelongsToTeam(Model $model, Team $team): bool
    {
        if ($model instanceof PlatformAccessGrant) {
            return true;
        }

        $attributes = $model->getAttributes();
        $recognized = false;

        if (array_key_exists('team_id', $attributes)) {
            $recognized = true;

            if ((string) $attributes['team_id'] !== (string) $team->id) {
                return false;
            }
        }

        if ($applicationId = $this->relationshipId($attributes, 'application_id')) {
            $recognized = true;

            if (! Application::query()->whereKey($applicationId)->where('team_id', $team->id)->exists()) {
                return false;
            }
        }

        if ($projectId = $this->relationshipId($attributes, 'project_id')) {
            $recognized = true;

            if (! Project::query()
                ->whereKey($projectId)
                ->whereHas('application', fn ($query) => $query->where('team_id', $team->id))
                ->exists()) {
                return false;
            }
        }

        if ($agentId = $this->relationshipId($attributes, 'agent_id')) {
            $recognized = true;

            if (! Agent::query()
                ->whereKey($agentId)
                ->whereHas('project.application', fn ($query) => $query->where('team_id', $team->id))
                ->exists()) {
                return false;
            }
        }

        if ($toolId = $this->relationshipId($attributes, 'tool_contract_id')) {
            $recognized = true;

            if (! ToolContract::query()->whereKey($toolId)->where('team_id', $team->id)->exists()) {
                return false;
            }
        }

        if ($sourceId = $this->relationshipId($attributes, 'knowledge_source_id')) {
            $recognized = true;

            if (! KnowledgeSource::query()->whereKey($sourceId)->where('team_id', $team->id)->exists()) {
                return false;
            }
        }

        if ($datasetId = $this->relationshipId($attributes, 'evaluation_dataset_id')) {
            $recognized = true;

            if (! EvaluationDataset::query()->whereKey($datasetId)->where('team_id', $team->id)->exists()) {
                return false;
            }
        }

        if ($endpointId = $this->relationshipId($attributes, 'webhook_endpoint_id')) {
            $recognized = true;

            if (! WebhookEndpoint::query()
                ->whereKey($endpointId)
                ->whereHas('application', fn ($query) => $query->where('team_id', $team->id))
                ->exists()) {
                return false;
            }
        }

        if ($model instanceof QuotaLimit
            && ! $this->quotaSubjectBelongsToTeam($team, $model->scope, $model->subject_id)) {
            return false;
        }

        if ($model instanceof ApprovalRequest && $model->subject_id !== null) {
            $subject = $model->subject()->first();

            if (! $subject instanceof Model || ! $this->modelBelongsToTeam($subject, $team)) {
                return false;
            }
        }

        return $recognized;
    }

    private function quotaSubjectBelongsToTeam(
        Team $team,
        QuotaScope $scope,
        ?string $subjectId,
        bool $lockForUpdate = false,
    ): bool {
        if ($scope === QuotaScope::Platform) {
            return $subjectId === null || $subjectId === '';
        }

        if ($subjectId === null || $subjectId === '') {
            return false;
        }

        $query = match ($scope) {
            QuotaScope::Application => Application::query()
                ->whereKey($subjectId)
                ->where('team_id', $team->id),
            QuotaScope::Project => Project::query()
                ->whereKey($subjectId)
                ->whereHas('application', fn ($query) => $query->where('team_id', $team->id)),
            QuotaScope::Agent => Agent::query()
                ->whereKey($subjectId)
                ->whereHas('project.application', fn ($query) => $query->where('team_id', $team->id)),
            QuotaScope::Model => LlmProvider::query()
                ->whereKey($subjectId)
                ->where('team_id', $team->id),
        };

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first() instanceof Model;
    }

    /**
     * Resolve an active application under the selected tenant.
     */
    private function applicationForTeam(Team $team, string $applicationId): Application
    {
        $application = Application::query()
            ->whereKey($applicationId)
            ->where('team_id', $team->id)
            ->lockForUpdate()
            ->first();

        if (! $application instanceof Application) {
            $this->fail('application_id', 'The selected application does not belong to the current tenant.');
        }

        return $application;
    }

    /**
     * @param  iterable<int, string>  $toolIds
     */
    private function assertAgentToolsForProject(Project $project, iterable $toolIds): void
    {
        $project->loadMissing('application');
        $ids = $this->uniqueIds($toolIds);
        $tools = ToolContract::query()
            ->whereIn('id', $ids)
            ->where('team_id', $project->application->team_id)
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        foreach ($ids as $index => $toolId) {
            $tool = $tools->get($toolId);

            if (! $tool instanceof ToolContract || ! $this->toolIsEligibleForProject($tool, $project)) {
                $this->fail("tool_ids.{$index}", 'The selected tool is not active and eligible for this project.');
            }
        }
    }

    private function providerIsEligibleForProject(Project $project, string $providerId, bool $lockForUpdate = false): bool
    {
        $project->loadMissing('application');
        $query = $project->llmProviders()
            ->whereKey($providerId)
            ->where('llm_providers.team_id', $project->application->team_id);

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $provider = $query->first();

        return $provider instanceof LlmProvider
            && $provider->status === LlmStatus::Approved
            && $provider->isVerified()
            && $provider->isAvailableIn($project->environment->value);
    }

    private function providerBelongsToProject(Project $project, string $providerId): bool
    {
        $project->loadMissing('application');

        return $project->llmProviders()
            ->whereKey($providerId)
            ->where('llm_providers.team_id', $project->application->team_id)
            ->exists();
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function runtimeBackingResourceIsEligible(string $modelClass, ?string $id, Project $project): bool
    {
        if ($id === null || $id === '') {
            return true;
        }

        $resource = $modelClass::query()
            ->whereKey($id)
            ->where('team_id', $project->application->team_id)
            ->first();

        if (! $resource instanceof Model) {
            return false;
        }

        $applicationId = $resource->getAttribute('application_id');

        return ($applicationId === null || (string) $applicationId === (string) $project->application_id)
            && $this->backingResourceIsEligible($resource, $project->application);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function runtimeBackingResourceBelongsToProject(string $modelClass, ?string $id, Project $project): bool
    {
        if ($id === null || $id === '') {
            return true;
        }

        $resource = $modelClass::query()
            ->whereKey($id)
            ->where('team_id', $project->application->team_id)
            ->first();

        if (! $resource instanceof Model) {
            return false;
        }

        $applicationId = $resource->getAttribute('application_id');

        return $applicationId === null || (string) $applicationId === (string) $project->application_id;
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private function assertOwnedBackingResource(
        string $modelClass,
        string $attribute,
        mixed $id,
        Team $team,
        ?Application $application,
    ): void {
        if (! is_string($id) || $id === '') {
            return;
        }

        $resource = $modelClass::query()
            ->whereKey($id)
            ->where('team_id', $team->id)
            ->lockForUpdate()
            ->first();

        $resourceApplicationId = $resource?->getAttribute('application_id');

        if (! $resource instanceof Model
            || ($application instanceof Application
                && $resourceApplicationId !== null
                && $resourceApplicationId !== $application->id)
            || ($application === null && $resourceApplicationId !== null)
            || ! $this->backingResourceIsEligible($resource, $application)) {
            $this->fail($attribute, 'The selected backing resource is not eligible for this tenant and application.');
        }
    }

    private function backingResourceIsEligible(Model $resource, ?Application $application): bool
    {
        return match (true) {
            $resource instanceof McpConnector => $application instanceof Application
                ? $resource->isAvailableIn($application->environment->value)
                : $resource->isActive(),
            $resource instanceof KnowledgeSource => $application instanceof Application
                ? $resource->isAvailableIn($application->environment->value)
                : $resource->isActive(),
            $resource instanceof DataSource => $application instanceof Application
                ? $resource->isAvailableIn($application->environment->value)
                : $resource->isActive(),
            default => false,
        };
    }

    /**
     * @return array<int, string>
     */
    private function uniqueIds(mixed $ids): array
    {
        return collect(is_iterable($ids) ? $ids : [])
            ->filter(fn (mixed $id): bool => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function relationshipId(array $attributes, string $attribute): ?string
    {
        $value = $attributes[$attribute] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function effectiveAttribute(array $data, string $attribute, mixed $existing): mixed
    {
        return array_key_exists($attribute, $data) ? $data[$attribute] : $existing;
    }

    private function fail(string $attribute, string $message): never
    {
        throw ValidationException::withMessages([$attribute => $message]);
    }
}
