<?php

namespace App\Support\Governance;

use App\Models\ApprovalRequest;
use App\Models\DataSource;
use App\Models\KnowledgeSource;
use App\Models\McpConnector;
use App\Models\ModelRoutingPolicy;
use App\Models\QuotaLimit;
use App\Models\Team;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use Illuminate\Database\Eloquent\Model;

class TenantAssociationIntegrityScanner
{
    public function __construct(private readonly TenantRelationshipGuard $relationships) {}

    /**
     * Inspect persisted tenant relationships without mutating or quarantining data.
     *
     * @return array<int, array{code: string, model: string, id: string, message: string}>
     */
    public function scan(): array
    {
        $findings = [];

        foreach ([
            ApprovalRequest::class,
            DataSource::class,
            KnowledgeSource::class,
            McpConnector::class,
            ModelRoutingPolicy::class,
            QuotaLimit::class,
            ToolContract::class,
        ] as $modelClass) {
            foreach ($modelClass::query()->cursor() as $model) {
                $team = Team::query()->find($model->getAttribute('team_id'));

                if (! $team instanceof Team || ! $this->relationships->modelBelongsToTeam($model, $team)) {
                    $findings[] = $this->finding(
                        'tenant_parent_mismatch',
                        $model,
                        'The record has a parent or subject outside its declared tenant.',
                    );
                }
            }
        }

        foreach (ToolAssignment::query()->with(['agent.project.application', 'project.application', 'toolContract'])->cursor() as $assignment) {
            $team = $assignment->agent?->project->application->team
                ?? $assignment->project?->application->team
                ?? $assignment->toolContract->team;

            if (! $this->relationships->modelBelongsToTeam($assignment, $team)) {
                $findings[] = $this->finding(
                    'tool_assignment_mismatch',
                    $assignment,
                    'The tool assignment crosses a tenant or parent boundary.',
                );
            }
        }

        foreach (ToolImplementation::query()->with('application.team')->cursor() as $implementation) {
            if (! $this->relationships->modelBelongsToTeam($implementation, $implementation->application->team)) {
                $findings[] = $this->finding(
                    'tool_implementation_mismatch',
                    $implementation,
                    'The implementation tool and application do not share a tenant.',
                );
            }
        }

        foreach (ModelRoutingPolicy::query()->with('agent.project.application')->cursor() as $policy) {
            $project = $policy->agent->project;

            foreach ($policy->candidateProviderIds() as $providerId) {
                if (! $project->llmProviders()
                    ->whereKey($providerId)
                    ->where('llm_providers.team_id', $project->application->team_id)
                    ->exists()) {
                    $findings[] = $this->finding(
                        'routing_provider_mismatch',
                        $policy,
                        "Routing candidate {$providerId} is foreign to or not approved for the agent project.",
                    );
                }
            }
        }

        return $findings;
    }

    /**
     * @return array{code: string, model: string, id: string, message: string}
     */
    private function finding(string $code, Model $model, string $message): array
    {
        return [
            'code' => $code,
            'model' => $model::class,
            'id' => (string) $model->getKey(),
            'message' => $message,
        ];
    }
}
