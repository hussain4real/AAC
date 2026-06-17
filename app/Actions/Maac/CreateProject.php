<?php

namespace App\Actions\Maac;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Support\Slug;
use Illuminate\Support\Facades\DB;

class CreateProject
{
    /**
     * Create a project and sync its approved LLM catalog entries when supplied.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(array $data): Project
    {
        $shouldSyncLlmProviders = array_key_exists('llm_provider_ids', $data);
        $llmProviderIds = $data['llm_provider_ids'] ?? [];
        unset($data['llm_provider_ids']);

        return DB::transaction(function () use ($data, $shouldSyncLlmProviders, $llmProviderIds): Project {
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
