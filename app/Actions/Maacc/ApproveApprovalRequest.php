<?php

namespace App\Actions\Maacc;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\CredentialStatus;
use App\Enums\DataSourceStatus;
use App\Enums\KnowledgeSourceStatus;
use App\Enums\LlmStatus;
use App\Exceptions\ApprovalBlockedException;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\ApprovalRequest;
use App\Models\Credential;
use App\Models\DataSource;
use App\Models\KnowledgeSource;
use App\Models\LlmProvider;
use App\Models\ToolContract;
use App\Models\User;
use App\Support\Governance\ApprovalGate;
use App\Support\Runtime\AgentRunner;
use App\Support\Sdk\SdkClientManager;
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
        private readonly SdkClientManager $sdkClients,
    ) {}

    /**
     * Approve the request and apply its effect within a transaction.
     *
     * @throws ApprovalBlockedException
     */
    public function handle(ApprovalRequest $request, User $decider, ?string $note = null): ApprovalRequest
    {
        return DB::transaction(function () use ($request, $decider, $note): ApprovalRequest {
            $locked = ApprovalRequest::query()->lockForUpdate()->findOrFail($request->id);

            if (! $locked->isPending()) {
                return $locked;
            }

            if ($locked->requested_by !== null && $locked->requested_by === $decider->id) {
                throw new ApprovalBlockedException(['The requester cannot approve their own change.']);
            }

            $this->gate->ensureSatisfied($locked);
            $this->applyEffect($locked, $decider);

            $locked->update([
                'status' => ApprovalStatus::Approved,
                'pending_key' => null,
                'decided_by' => $decider->id,
                'decided_label' => $decider->name,
                'decision_note' => $note,
                'decided_at' => Carbon::now(),
            ]);

            return $locked;
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
            ApprovalType::CredentialChange => $this->applyCredentialChange($request),
        };
    }

    /**
     * Apply a staged credential creation or rotation only after approval.
     */
    private function applyCredentialChange(ApprovalRequest $request): void
    {
        $credential = $request->subject;

        if (! $credential instanceof Credential) {
            return;
        }

        $change = ($request->metadata ?? [])['change'] ?? null;

        if ($change === 'creation') {
            $this->sdkClients->activate($credential);
            $credential->update([
                'status' => CredentialStatus::Active,
                'revoked_at' => null,
            ]);
        }

        if ($change === 'rotation') {
            $secret = ($request->encrypted_payload ?? [])['secret'] ?? null;

            if (is_string($secret)) {
                $this->sdkClients->applyApprovedSecret($credential, $secret);
                $credential->update([
                    'status' => CredentialStatus::Active,
                    'rotated_at' => Carbon::now(),
                    'revoked_at' => null,
                ]);
            }
        }

        $request->update(['encrypted_payload' => null]);
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
