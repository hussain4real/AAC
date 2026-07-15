<?php

namespace App\Actions\Maacc;

use App\Enums\AgentStatus;
use App\Exceptions\ApprovalBlockedException;
use App\Models\Agent;
use App\Models\User;
use App\Support\Governance\AgentReadinessGate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PublishAgent
{
    public function __construct(private readonly AgentReadinessGate $readiness) {}

    /**
     * Publish the agent and snapshot its current configuration.
     */
    public function handle(Agent $agent, User $publisher): Agent
    {
        return DB::transaction(function () use ($agent, $publisher): Agent {
            $locked = Agent::query()->lockForUpdate()->findOrFail($agent->id);
            $locked->loadMissing('project.application');
            $blockers = $this->readiness->blockers(
                $locked,
                $locked->project->application,
                $locked->project->environment,
                requirePublished: false,
                requireEvaluations: true,
                requireImmutableVersion: false,
            );

            if ($blockers !== []) {
                throw new ApprovalBlockedException($blockers);
            }

            $nextVersion = 'v'.((int) ltrim($locked->version, 'v') + 1);
            $publishedAt = Carbon::now();
            $configurationHash = $this->readiness->configurationHash($locked);

            $version = $locked->versions()->create([
                'version' => $nextVersion,
                'system_prompt' => $locked->system_prompt,
                'llm_provider_id' => $locked->llm_provider_id,
                'temperature' => $locked->temperature,
                'max_tokens' => $locked->max_tokens,
                'settings' => [
                    'temperature' => $locked->temperature,
                    'max_tokens' => $locked->max_tokens,
                    'configuration_hash' => $configurationHash,
                ],
                'status' => AgentStatus::Published->value,
                'published_at' => $publishedAt,
                'published_by' => $publisher->id,
            ]);

            $locked->update([
                'status' => AgentStatus::Published->value,
                'published_at' => $publishedAt,
                'version' => $nextVersion,
                'current_version_id' => $version->id,
            ]);

            return $locked;
        });
    }
}
