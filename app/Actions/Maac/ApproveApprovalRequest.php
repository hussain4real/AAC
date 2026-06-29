<?php

namespace App\Actions\Maac;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\DataSourceStatus;
use App\Enums\KnowledgeSourceStatus;
use App\Enums\LlmStatus;
use App\Exceptions\ApprovalBlockedException;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\ApprovalRequest;
use App\Models\DataSource;
use App\Models\KnowledgeSource;
use App\Models\LlmProvider;
use App\Models\ToolContract;
use App\Models\User;
use App\Support\Governance\ApprovalGate;
use App\Support\Runtime\AgentRunner;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Approves a governance approval request, applying the gated change (publishing
 * the agent, activating the tool contract, or promoting the model into the
 * requested environment) and recording the decision.
 */
class ApproveApprovalRequest
{
    public function __construct(
        private readonly PublishAgent $publisher,
        private readonly ApprovalGate $gate,
    ) {}

    /**
     * Approve the request and apply its effect within a transaction.
     *
     * @throws ApprovalBlockedException
     */
    public function handle(ApprovalRequest $request, User $decider, ?string $note = null): ApprovalRequest
    {
        $this->gate->ensureSatisfied($request);

        return DB::transaction(function () use ($request, $decider, $note): ApprovalRequest {
            $this->applyEffect($request, $decider);

            $request->update([
                'status' => ApprovalStatus::Approved,
                'decided_by' => $decider->id,
                'decided_label' => $decider->name,
                'decision_note' => $note,
                'decided_at' => Carbon::now(),
            ]);

            return $request;
        });
    }

    /**
     * Apply the change the approval gates, based on its type.
     */
    private function applyEffect(ApprovalRequest $request, User $decider): void
    {
        match ($request->type) {
            ApprovalType::AgentPublication => $this->publishAgent($request, $decider),
            ApprovalType::ToolContract => $this->activateTool($request),
            ApprovalType::ModelAccess => $this->promoteModel($request),
            ApprovalType::KnowledgeIngestion => $this->activateSource($request),
            ApprovalType::DataSourceAccess => $this->activateDataSource($request),
            ApprovalType::RuntimeAction => $this->resumeRun($request),
            ApprovalType::CredentialChange => null,
        };
    }

    /**
     * Mark an approved sensitive run running again so a worker can drive it. The
     * controller dispatches the worker after the approval transaction commits.
     */
    private function resumeRun(ApprovalRequest $request): void
    {
        if ($request->subject instanceof AgentRun) {
            app(AgentRunner::class)->approveRuntime($request->subject);
        }
    }

    /**
     * Publish the gated agent, if it still exists.
     */
    private function publishAgent(ApprovalRequest $request, User $decider): void
    {
        if ($request->subject instanceof Agent) {
            $this->publisher->handle($request->subject, $decider);
        }
    }

    /**
     * Activate the gated tool contract, if it still exists.
     */
    private function activateTool(ApprovalRequest $request): void
    {
        if ($request->subject instanceof ToolContract) {
            $request->subject->update(['status' => 'Active']);
        }
    }

    /**
     * Activate the gated knowledge source, if it still exists.
     */
    private function activateSource(ApprovalRequest $request): void
    {
        if ($request->subject instanceof KnowledgeSource) {
            $request->subject->update(['status' => KnowledgeSourceStatus::Active]);
        }
    }

    /**
     * Activate the gated read-only data source, if it still exists.
     */
    private function activateDataSource(ApprovalRequest $request): void
    {
        if ($request->subject instanceof DataSource) {
            $request->subject->update(['status' => DataSourceStatus::Active]);
        }
    }

    /**
     * Add the requested environment to the gated model's availability.
     */
    private function promoteModel(ApprovalRequest $request): void
    {
        $model = $request->subject;

        if (! $model instanceof LlmProvider || $request->environment === null) {
            return;
        }

        $environments = $model->environments;

        if (! in_array($request->environment->value, $environments, true)) {
            $environments[] = $request->environment->value;
        }

        $model->update(['environments' => $environments, 'status' => LlmStatus::Approved]);
    }
}
