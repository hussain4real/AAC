<?php

namespace App\Support\Governance;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\Environment;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\ApprovalRequest;
use App\Models\Credential;
use App\Models\DataSource;
use App\Models\KnowledgeSource;
use App\Models\LlmProvider;
use App\Models\Team;
use App\Models\ToolContract;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Opens governance approval requests for the changes the BRS gates behind owner
 * review: sensitive tool contracts, agent publication, model environment
 * promotion, and production credential changes. Opening is idempotent — a
 * pending request for the same subject and type is returned rather than
 * duplicated.
 */
class ApprovalManager
{
    /**
     * Open (or return the existing pending) approval request for a subject.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function open(Team $team, ApprovalType $type, ?Model $subject, array $attributes = []): ApprovalRequest
    {
        $query = $team->approvalRequests()->pending()->where('type', $type);

        if ($subject !== null) {
            $query->where('subject_type', $subject->getMorphClass())
                ->where('subject_id', $subject->getKey());
        }

        $existing = $query->first();

        if ($existing instanceof ApprovalRequest) {
            return $existing;
        }

        return $team->approvalRequests()->create([
            ...$attributes,
            'type' => $type,
            'status' => ApprovalStatus::Pending,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
        ]);
    }

    /**
     * Request approval to publish an agent to the given environment.
     */
    public function requestAgentPublication(Agent $agent, User $requester, Environment $target): ApprovalRequest
    {
        $agent->loadMissing('project.application');
        $application = $agent->project->application;

        return $this->open($application->team, ApprovalType::AgentPublication, $agent, [
            'application_id' => $application->id,
            'project_id' => $agent->project_id,
            'title' => $agent->name,
            'summary' => "Publish {$agent->name} to {$target->label()}.",
            'sensitivity' => $agent->sensitivity,
            'environment' => $target,
            'requested_by' => $requester->id,
            'requested_label' => $requester->name,
        ]);
    }

    /**
     * Request approval of a sensitive client-side tool contract.
     */
    public function requestToolContractApproval(ToolContract $tool, User $requester): ApprovalRequest
    {
        return $this->open($tool->team, ApprovalType::ToolContract, $tool, [
            'application_id' => $tool->application_id,
            'title' => $tool->name,
            'summary' => "Approve tool contract {$tool->name} for production use.",
            'sensitivity' => $tool->sensitivity,
            'requested_by' => $requester->id,
            'requested_label' => $requester->name,
        ]);
    }

    /**
     * Request approval to promote a model into an environment.
     */
    public function requestModelAccess(LlmProvider $model, User $requester, Environment $target): ApprovalRequest
    {
        return $this->open($model->team, ApprovalType::ModelAccess, $model, [
            'title' => "{$model->name} → {$target->label()}",
            'summary' => "Promote {$model->name} to {$target->label()}.",
            'sensitivity' => $model->sensitivity,
            'environment' => $target,
            'requested_by' => $requester->id,
            'requested_label' => $requester->name,
        ]);
    }

    /**
     * Request approval to ingest a sensitive knowledge (RAG) source.
     */
    public function requestKnowledgeIngestion(KnowledgeSource $source, User $requester): ApprovalRequest
    {
        return $this->open($source->team, ApprovalType::KnowledgeIngestion, $source, [
            'application_id' => $source->application_id,
            'title' => $source->name,
            'summary' => "Approve ingestion of the knowledge source {$source->name}.",
            'sensitivity' => $source->sensitivity,
            'requested_by' => $requester->id,
            'requested_label' => $requester->name,
        ]);
    }

    /**
     * Request approval to access a sensitive read-only data source.
     */
    public function requestDataSourceAccess(DataSource $source, User $requester): ApprovalRequest
    {
        return $this->open($source->team, ApprovalType::DataSourceAccess, $source, [
            'application_id' => $source->application_id,
            'title' => $source->name,
            'summary' => "Approve access to the read-only data source {$source->name}.",
            'sensitivity' => $source->sensitivity,
            'requested_by' => $requester->id,
            'requested_label' => $requester->name,
        ]);
    }

    /**
     * Open a human-in-the-loop approval for a sensitive run paused by the runtime.
     */
    public function requestRuntimeApproval(AgentRun $run, Team $team): ApprovalRequest
    {
        return $this->open($team, ApprovalType::RuntimeAction, $run, [
            'application_id' => $run->application_id,
            'project_id' => $run->project_id,
            'title' => "Run {$run->slug}",
            'summary' => "Approve run {$run->slug} of {$run->agent->name} in {$run->environment?->label()}.",
            'sensitivity' => $run->sensitivity,
            'environment' => $run->environment,
        ]);
    }

    /**
     * Request approval for a production credential change.
     */
    public function requestCredentialChange(Credential $credential, User $requester, string $change): ApprovalRequest
    {
        $credential->loadMissing('application');
        $application = $credential->application;

        return $this->open($application->team, ApprovalType::CredentialChange, $credential, [
            'application_id' => $application->id,
            'title' => "{$application->name} — {$credential->label}",
            'summary' => "Approve credential {$change} for {$application->name} ({$credential->environment->label()}).",
            'sensitivity' => null,
            'environment' => $credential->environment,
            'requested_by' => $requester->id,
            'requested_label' => $requester->name,
        ]);
    }
}
