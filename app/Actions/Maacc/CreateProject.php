<?php

namespace App\Actions\Maacc;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Models\Team;
use App\Support\Governance\TenantRelationshipGuard;
use App\Support\Slug;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateProject
{
    public function __construct(private readonly TenantRelationshipGuard $relationships) {}

    /**
     * Create a project and sync its approved LLM catalog entries when supplied.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data): Project
    {
        $data = Arr::only($data, [
            'application_id',
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

        return DB::transaction(function () use ($team, $data, $shouldSyncLlmProviders, $llmProviderIds): Project {
            $this->relationships->assertProjectRelationships($team, [
                ...$data,
                'llm_provider_ids' => $llmProviderIds,
            ]);

            $project = Project::create([
                ...$data,
                'slug' => Slug::unique('projects', (string) $data['name']),
                'status' => $data['status'] ?? ProjectStatus::Active->value,
            ]);

            if ($shouldSyncLlmProviders) {
                $project->llmProviders()->sync($llmProviderIds);
            }

            return $project;
        });
    }
}
