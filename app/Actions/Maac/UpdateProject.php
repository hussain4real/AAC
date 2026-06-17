<?php

namespace App\Actions\Maac;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

class UpdateProject
{
    /**
     * Update a project and sync its approved LLM catalog entries when supplied.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Project $project, array $data): Project
    {
        $shouldSyncLlmProviders = array_key_exists('llm_provider_ids', $data);
        $llmProviderIds = $data['llm_provider_ids'] ?? [];
        unset($data['llm_provider_ids']);

        return DB::transaction(function () use ($project, $data, $shouldSyncLlmProviders, $llmProviderIds): Project {
            $project->update($data);

            if ($shouldSyncLlmProviders) {
                $project->llmProviders()->sync($llmProviderIds);
            }

            return $project;
        });
    }
}
