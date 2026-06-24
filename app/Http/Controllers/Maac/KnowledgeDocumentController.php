<?php

namespace App\Http\Controllers\Maac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StoreKnowledgeDocumentRequest;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSource;
use App\Support\Runtime\Knowledge\KnowledgeIndexer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

/**
 * Console ingestion of documents into a knowledge source. Adding a document runs
 * it through the indexing pipeline (chunk + tokenize); removing one re-indexes
 * the source so its freshness metadata stays accurate.
 */
class KnowledgeDocumentController extends Controller
{
    /**
     * Ingest and index a new document into the source.
     */
    public function store(StoreKnowledgeDocumentRequest $request, string $currentTeam, KnowledgeSource $knowledgeSource, KnowledgeIndexer $indexer): RedirectResponse
    {
        Gate::authorize('update', $knowledgeSource);

        $indexer->ingestDocument($knowledgeSource, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document ingested and indexed.']);

        return back();
    }

    /**
     * Remove a document and re-index the source.
     */
    public function destroy(Request $request, string $currentTeam, KnowledgeDocument $knowledgeDocument, KnowledgeIndexer $indexer): RedirectResponse
    {
        $source = $knowledgeDocument->source;

        Gate::authorize('update', $source);

        $knowledgeDocument->delete();
        $indexer->reindex($source);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document removed.']);

        return back();
    }
}
