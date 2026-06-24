<?php

namespace App\Http\Controllers\Maac;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maac\StoreKnowledgeDocumentRequest;
use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSource;
use App\Support\Runtime\Knowledge\KnowledgeExtractionException;
use App\Support\Runtime\Knowledge\KnowledgeIndexer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

/**
 * Console ingestion of documents into a knowledge source. A document can be
 * pasted as plain text or uploaded as a file; either way it runs through the
 * indexing pipeline (chunk + tokenize). Uploaded files are saved to storage,
 * which the indexer reads from to extract their text. Removing a document
 * re-indexes the source so its freshness metadata stays accurate.
 */
class KnowledgeDocumentController extends Controller
{
    /**
     * Ingest and index a new document into the source.
     */
    public function store(StoreKnowledgeDocumentRequest $request, string $currentTeam, KnowledgeSource $knowledgeSource, KnowledgeIndexer $indexer): RedirectResponse
    {
        Gate::authorize('update', $knowledgeSource);

        $validated = $request->validated();

        if ($request->hasFile('document')) {
            /** @var UploadedFile $file */
            $file = $request->file('document');
            $this->ingestUploadedFile($file, $knowledgeSource, $indexer, $validated);
        } else {
            $indexer->ingestDocument($knowledgeSource, $validated);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document ingested and indexed.']);

        return back();
    }

    /**
     * Remove a document (and its stored file, if uploaded) and re-index the source.
     */
    public function destroy(Request $request, string $currentTeam, KnowledgeDocument $knowledgeDocument, KnowledgeIndexer $indexer): RedirectResponse
    {
        $source = $knowledgeDocument->source;

        Gate::authorize('update', $source);

        if ($knowledgeDocument->isUploaded()) {
            Storage::disk((string) $knowledgeDocument->disk)->delete((string) $knowledgeDocument->storage_path);
        }

        $knowledgeDocument->delete();
        $indexer->reindex($source);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Document removed.']);

        return back();
    }

    /**
     * Persist the uploaded file to storage and index it. If text extraction
     * fails (corrupt/unreadable file) the stored file is cleaned up and the
     * failure is surfaced as a validation error on the upload field.
     *
     * @param  array<string, mixed>  $validated
     */
    private function ingestUploadedFile(UploadedFile $file, KnowledgeSource $source, KnowledgeIndexer $indexer, array $validated): void
    {
        $disk = (string) config('filesystems.default');
        $extension = strtolower($file->getClientOriginalExtension());
        $path = "knowledge/{$source->id}/".Str::uuid()->toString().".{$extension}";

        Storage::disk($disk)->put($path, $file->getContent());

        try {
            $indexer->ingestStoredDocument($source, [
                'title' => $validated['title'],
                'uri' => $validated['uri'] ?? null,
                'disk' => $disk,
                'storage_path' => $path,
                'original_filename' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'file_size' => (int) $file->getSize(),
                'metadata' => $validated['metadata'] ?? null,
            ]);
        } catch (KnowledgeExtractionException $exception) {
            Storage::disk($disk)->delete($path);

            throw ValidationException::withMessages(['document' => $exception->getMessage()]);
        }
    }
}
