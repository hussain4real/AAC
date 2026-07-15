<?php

namespace App\Actions\Maacc;

use App\Models\Project;
use App\Support\Governance\TenantRelationshipGuard;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class UpdateProject
{
    public function __construct(private readonly TenantRelationshipGuard $relationships) {}

    /**
     * Update a project and sync its approved LLM catalog entries when supplied.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Project $project, array $data): Project
    {
        $data = Arr::only($data, [
            'name',
            'environment',
            'description',
            'business_owner',
            'technical_owner',
            'status',
            'llm_provider_ids',
        ]);
        $shouldSyncLlmProviders = array_key_exists('llm_provider_ids', $data);
        $llmProviderIds = $data['llm_provider_ids'] ?? [];
        unset($data['llm_provider_ids']);

        return DB::transaction(function () use ($project, $data, $shouldSyncLlmProviders, $llmProviderIds): Project {
            $project = $this->relationships->projectForWrite($project);
            $effectiveLlmProviderIds = $shouldSyncLlmProviders
                ? $llmProviderIds
                : $project->llmProviders()->pluck('llm_providers.id')->all();
            $this->relationships->assertProjectRelationships($project->application->team, [
                ...$data,
                'llm_provider_ids' => $effectiveLlmProviderIds,
            ], $project);

            $project->update($data);

            if ($shouldSyncLlmProviders) {
                $project->llmProviders()->sync($llmProviderIds);
            }

            return $project;
        });
    }
}
