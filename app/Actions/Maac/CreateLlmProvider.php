<?php

namespace App\Actions\Maac;

use App\Enums\LlmStatus;
use App\Models\LlmProvider;
use App\Models\Team;
use App\Support\Slug;

class CreateLlmProvider
{
    /**
     * Add a model to the team's approved LLM catalog.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data): LlmProvider
    {
        return LlmProvider::create([
            ...$data,
            'team_id' => $team->id,
            'slug' => Slug::unique('llm_providers', (string) $data['code']),
            'status' => $data['status'] ?? LlmStatus::Approved->value,
        ]);
    }
}
