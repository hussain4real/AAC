<?php

namespace App\Actions\Maac;

use App\Enums\AgentStatus;
use App\Models\Agent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PublishAgent
{
    /**
     * Publish the agent and snapshot its current configuration.
     */
    public function handle(Agent $agent, User $publisher): Agent
    {
        return DB::transaction(function () use ($agent, $publisher): Agent {
            $nextVersion = 'v'.((int) ltrim($agent->version, 'v') + 1);
            $publishedAt = Carbon::now();

            $version = $agent->versions()->create([
                'version' => $nextVersion,
                'system_prompt' => $agent->system_prompt,
                'llm_provider_id' => $agent->llm_provider_id,
                'temperature' => $agent->temperature,
                'max_tokens' => $agent->max_tokens,
                'settings' => ['temperature' => $agent->temperature, 'max_tokens' => $agent->max_tokens],
                'status' => AgentStatus::Published->value,
                'published_at' => $publishedAt,
                'published_by' => $publisher->id,
            ]);

            $agent->update([
                'status' => AgentStatus::Published->value,
                'published_at' => $publishedAt,
                'version' => $nextVersion,
                'current_version_id' => $version->id,
            ]);

            return $agent;
        });
    }
}
