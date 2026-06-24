<?php

namespace App\Http\Controllers\Maac;

use App\Enums\KnowledgeSourceStatus;
use App\Enums\Sensitivity;
use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StoreKnowledgeSourceRequest;
use App\Http\Requests\Maac\UpdateKnowledgeSourceRequest;
use App\Models\KnowledgeSource;
use App\Support\Governance\ApprovalManager;
use App\Support\Runtime\Knowledge\KnowledgeIndexer;
use App\Support\Slug;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Console management of knowledge (RAG) sources: register a source, manage its
 * lifecycle, and re-index it. A sensitive source (Confidential+) or one flagged
 * for approval is created as a draft and opens an ingestion approval; the runtime
 * only retrieves from an active source.
 */
class KnowledgeSourceController extends Controller
{
    /**
     * Register a new knowledge source, gating a sensitive one behind ingestion
     * approval.
     */
    public function store(StoreKnowledgeSourceRequest $request, ApprovalManager $approvals): RedirectResponse
    {
        Gate::authorize('create', KnowledgeSource::class);

        $team = $request->user()->currentTeam()->firstOrFail();
        $data = $request->validated();
        $sensitivity = Sensitivity::from($data['sensitivity']);
        $gated = ($data['requires_approval'] ?? false) === true || $sensitivity->requiresMasking();

        $source = new KnowledgeSource([
            ...$data,
            'team_id' => $team->id,
            'slug' => Slug::unique('knowledge_sources', $data['name']),
            'status' => $gated ? KnowledgeSourceStatus::Draft : KnowledgeSourceStatus::Active,
            'requires_approval' => $gated,
            'created_by' => $request->user()?->getAuthIdentifier(),
        ]);
        $source->save();

        if ($gated) {
            $approvals->requestKnowledgeIngestion($source, $request->user());
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $gated
                ? 'Knowledge source created — pending ingestion approval before retrieval.'
                : 'Knowledge source created.',
        ]);

        return back();
    }

    /**
     * Update the given knowledge source.
     */
    public function update(UpdateKnowledgeSourceRequest $request, string $currentTeam, KnowledgeSource $knowledgeSource): RedirectResponse
    {
        Gate::authorize('update', $knowledgeSource);

        $knowledgeSource->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Knowledge source updated.']);

        return back();
    }

    /**
     * Rebuild the source's chunk index from its current documents.
     */
    public function reindex(string $currentTeam, KnowledgeSource $knowledgeSource, KnowledgeIndexer $indexer): RedirectResponse
    {
        Gate::authorize('update', $knowledgeSource);

        $indexer->reindex($knowledgeSource);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Knowledge source re-indexed.']);

        return back();
    }

    /**
     * Delete (soft delete) the given knowledge source.
     */
    public function destroy(Request $request, string $currentTeam, KnowledgeSource $knowledgeSource): RedirectResponse
    {
        Gate::authorize('delete', $knowledgeSource);

        $knowledgeSource->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Knowledge source removed.']);

        return back();
    }
}
