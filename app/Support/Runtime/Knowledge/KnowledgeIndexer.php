<?php

namespace App\Support\Runtime\Knowledge;

use App\Models\KnowledgeDocument;
use App\Models\KnowledgeSource;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * The knowledge indexing pipeline. It ingests a document into a source, splits
 * its body into retrievable chunks (paragraph-first, capped at a configurable
 * word window), tokenizes each chunk for the lexical retriever, and keeps the
 * source's freshness metadata (document/chunk counts + last indexed time) up to
 * date. Re-indexing a source rebuilds every document's chunks deterministically.
 */
class KnowledgeIndexer
{
    public function __construct(private readonly DocumentTextExtractor $extractor) {}

    /**
     * Ingest a new pasted-body document into the source and index it.
     *
     * @param  array{title?: string, uri?: string|null, body?: string, metadata?: array<string, mixed>|null}  $data
     */
    public function ingestDocument(KnowledgeSource $source, array $data): KnowledgeDocument
    {
        $body = (string) ($data['body'] ?? '');

        $document = $source->documents()->create([
            'title' => (string) ($data['title'] ?? 'Untitled document'),
            'uri' => $data['uri'] ?? null,
            'body' => $body,
            'checksum' => hash('sha256', $body),
            'metadata' => $data['metadata'] ?? null,
            'indexed_at' => null,
        ]);

        DB::transaction(function () use ($document, $source): void {
            $this->indexDocument($document);
            $this->refreshCounts($source);
        });

        return $document->refresh();
    }

    /**
     * Ingest an uploaded document whose source file already lives in storage.
     * The whole create-and-index runs in one transaction so a failed text
     * extraction (corrupt/unreadable file) rolls the document row back.
     *
     * @param  array{title?: string, uri?: string|null, disk?: string, storage_path?: string, original_filename?: string|null, mime_type?: string|null, file_size?: int|null, metadata?: array<string, mixed>|null}  $data
     */
    public function ingestStoredDocument(KnowledgeSource $source, array $data): KnowledgeDocument
    {
        return DB::transaction(function () use ($source, $data): KnowledgeDocument {
            $document = $source->documents()->create([
                'title' => (string) ($data['title'] ?? 'Untitled document'),
                'uri' => $data['uri'] ?? null,
                'body' => '',
                'checksum' => '',
                'disk' => $data['disk'] ?? null,
                'storage_path' => $data['storage_path'] ?? null,
                'original_filename' => $data['original_filename'] ?? null,
                'mime_type' => $data['mime_type'] ?? null,
                'file_size' => $data['file_size'] ?? null,
                'metadata' => $data['metadata'] ?? null,
                'indexed_at' => null,
            ]);

            $this->indexDocument($document);
            $this->refreshCounts($source);

            return $document->refresh();
        });
    }

    /**
     * Rebuild the chunk index for every document in the source.
     */
    public function reindex(KnowledgeSource $source): void
    {
        DB::transaction(function () use ($source): void {
            foreach ($source->documents()->get() as $document) {
                $this->indexDocument($document);
            }

            $this->refreshCounts($source);
        });
    }

    /**
     * Re-chunk and re-tokenize a single document, replacing its prior chunks.
     * For an uploaded document the body is re-extracted from its stored file
     * first, so storage stays the source of truth across re-indexing.
     */
    private function indexDocument(KnowledgeDocument $document): void
    {
        if ($document->storage_path !== null && $document->disk !== null) {
            $body = $this->extractor->extract(
                $document->disk,
                $document->storage_path,
                pathinfo($document->storage_path, PATHINFO_EXTENSION),
            );

            $document->forceFill(['body' => $body, 'checksum' => hash('sha256', $body)])->save();
        }

        $document->chunks()->delete();

        foreach ($this->chunk($document->body) as $ordinal => $content) {
            $tokens = Tokenizer::tokenize($content);

            $document->chunks()->create([
                'knowledge_source_id' => $document->knowledge_source_id,
                'ordinal' => $ordinal,
                'content' => $content,
                'tokens' => $tokens,
                'token_count' => count($tokens),
            ]);
        }

        $document->update(['indexed_at' => Date::now()]);
    }

    /**
     * Recompute the source's freshness metadata from its current documents.
     */
    private function refreshCounts(KnowledgeSource $source): void
    {
        $source->update([
            'document_count' => $source->documents()->count(),
            'chunk_count' => $source->chunks()->count(),
            'last_indexed_at' => Date::now(),
        ]);
    }

    /**
     * Split a document body into retrievable chunks: one per paragraph, with any
     * paragraph longer than the configured word window split into fixed windows.
     *
     * @return array<int, string>
     */
    private function chunk(string $body): array
    {
        $maxWords = max(1, (int) config('maac.runtime.knowledge.chunk_size', 120));
        $paragraphs = preg_split('/\n\s*\n/', trim($body)) ?: [];
        $chunks = [];

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim((string) $paragraph);

            if ($paragraph === '') {
                continue;
            }

            $words = preg_split('/\s+/', $paragraph) ?: [];

            if (count($words) <= $maxWords) {
                $chunks[] = $paragraph;

                continue;
            }

            foreach (array_chunk($words, $maxWords) as $window) {
                $chunks[] = implode(' ', $window);
            }
        }

        return $chunks;
    }
}
