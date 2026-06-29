<?php

namespace App\Http\Controllers\Maac;

use App\Actions\Maac\ApproveApprovalRequest;
use App\Actions\Maac\RejectApprovalRequest;
use App\Enums\ApprovalType;
use App\Enums\Environment;
use App\Exceptions\ApprovalBlockedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\DecideApprovalRequest;
use App\Http\Requests\Maac\RequestApprovalRequest;
use App\Jobs\AdvanceAgentRun;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\ApprovalRequest;
use App\Models\Credential;
use App\Models\DataSource;
use App\Models\KnowledgeSource;
use App\Models\LlmProvider;
use App\Models\Team;
use App\Models\ToolContract;
use App\Support\Governance\ApprovalManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ApprovalRequestController extends Controller
{
    /**
     * Open a governance approval request for a sensitive change.
     */
    public function store(RequestApprovalRequest $request, ApprovalManager $manager): RedirectResponse
    {
        Gate::authorize('create', ApprovalRequest::class);

        $team = $request->user()->currentTeam()->firstOrFail();
        $user = $request->user();
        $subject = (string) $request->validated('subject');
        $environment = $request->validated('environment') !== null
            ? Environment::from($request->validated('environment'))
            : Environment::Production;

        match (ApprovalType::from($request->validated('type'))) {
            ApprovalType::AgentPublication => $manager->requestAgentPublication($this->agent($team, $subject), $user, $environment),
            ApprovalType::ToolContract => $manager->requestToolContractApproval($this->tool($team, $subject), $user),
            ApprovalType::ModelAccess => $manager->requestModelAccess($this->model($team, $subject), $user, $environment),
            ApprovalType::CredentialChange => $manager->requestCredentialChange($this->credential($team, $subject), $user, (string) ($request->validated('change') ?? 'production change')),
            ApprovalType::KnowledgeIngestion => $manager->requestKnowledgeIngestion($this->source($team, $subject), $user),
            ApprovalType::DataSourceAccess => $manager->requestDataSourceAccess($this->dataSource($team, $subject), $user),
            ApprovalType::RuntimeAction => abort(422, 'Runtime approvals are opened by the runtime, not requested manually.'),
        };

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Approval requested.']);

        return back();
    }

    /**
     * Approve a pending request, applying the gated change.
     */
    public function approve(DecideApprovalRequest $request, string $currentTeam, ApprovalRequest $approvalRequest, ApproveApprovalRequest $action): RedirectResponse
    {
        Gate::authorize('decide', $approvalRequest);
        abort_unless($approvalRequest->isPending(), 409, 'This request has already been decided.');

        try {
            $approved = $action->handle($approvalRequest, $request->user(), $request->validated('note'));
        } catch (ApprovalBlockedException $exception) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Cannot approve — unmet prerequisites: '.implode(' ', $exception->blockers),
            ]);

            return back();
        }

        // A resumed runtime approval continues the paused run on a worker (the run
        // was marked running inside the approval transaction, which has committed).
        if ($approved->type === ApprovalType::RuntimeAction && $approved->subject instanceof AgentRun) {
            AdvanceAgentRun::dispatch($approved->subject);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Approval granted.']);

        return back();
    }

    /**
     * Reject a pending request.
     */
    public function reject(DecideApprovalRequest $request, string $currentTeam, ApprovalRequest $approvalRequest, RejectApprovalRequest $action): RedirectResponse
    {
        Gate::authorize('decide', $approvalRequest);
        abort_unless($approvalRequest->isPending(), 409, 'This request has already been decided.');

        $action->handle($approvalRequest, $request->user(), $request->validated('note'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Approval rejected.']);

        return back();
    }

    /**
     * Resolve a published-or-draft agent by slug within the team.
     */
    private function agent(Team $team, string $reference): Agent
    {
        return Agent::query()
            ->whereHas('project.application', fn ($query) => $query->where('team_id', $team->id))
            ->where(fn ($query) => $query->where('agent_slug', $reference)->orWhere('slug', $reference))
            ->firstOrFail();
    }

    /**
     * Resolve a tool contract by slug within the team.
     */
    private function tool(Team $team, string $reference): ToolContract
    {
        return $team->toolContracts()->where('slug', $reference)->firstOrFail();
    }

    /**
     * Resolve a model by slug within the team.
     */
    private function model(Team $team, string $reference): LlmProvider
    {
        return $team->llmProviders()->where('slug', $reference)->firstOrFail();
    }

    /**
     * Resolve a credential by id within the team.
     */
    private function credential(Team $team, string $reference): Credential
    {
        return Credential::query()
            ->whereHas('application', fn ($query) => $query->where('team_id', $team->id))
            ->where('id', $reference)
            ->firstOrFail();
    }

    /**
     * Resolve a knowledge source by slug within the team.
     */
    private function source(Team $team, string $reference): KnowledgeSource
    {
        return KnowledgeSource::query()
            ->where('team_id', $team->id)
            ->where('slug', $reference)
            ->firstOrFail();
    }

    /**
     * Resolve a read-only data source by slug within the team.
     */
    private function dataSource(Team $team, string $reference): DataSource
    {
        return DataSource::query()
            ->where('team_id', $team->id)
            ->where('slug', $reference)
            ->firstOrFail();
    }
}
