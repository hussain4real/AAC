<?php

namespace App\Actions\Maac;

use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\LlmStatus;
use App\Exceptions\ApprovalBlockedException;
use App\Models\Agent;
use App\Models\ApprovalRequest;
use App\Models\LlmProvider;
use App\Models\ToolContract;
use App\Models\User;
use App\Support\Governance\ApprovalGate;
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
            ApprovalType::CredentialChange => null,
        };
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
