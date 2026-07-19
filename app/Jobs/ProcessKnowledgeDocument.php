<?php

namespace App\Jobs;

use App\Enums\KnowledgeDocumentStatus;
use App\Models\KnowledgeDocument;
use App\Support\Runtime\Knowledge\DocumentUploadGuard;
use App\Support\Runtime\Knowledge\KnowledgeExtractionException;
use App\Support\Runtime\Knowledge\KnowledgeIndexer;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Scans and parses a quarantined knowledge upload on the isolated ingestion
 * queue. A content-policy rejection is terminal and remains quarantined; an
 * infrastructure crash is retried and repaired deterministically on failure.
 */
class ProcessKnowledgeDocument implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 120;

    public int $uniqueFor = 300;

    public bool $failOnTimeout = true;

    public function __construct(public KnowledgeDocument $document) {}

    public function handle(DocumentUploadGuard $guard, KnowledgeIndexer $indexer): void
    {
        $claimed = KnowledgeDocument::query()
            ->whereKey($this->document->id)
            ->whereIn('ingestion_status', [KnowledgeDocumentStatus::Pending->value, KnowledgeDocumentStatus::Failed->value])
            ->update([
                'ingestion_status' => KnowledgeDocumentStatus::Scanning,
                'processing_attempts' => DB::raw('processing_attempts + 1'),
                'quarantine_reason' => null,
            ]);

        if ($claimed !== 1) {
            return;
        }

        $document = $this->document->fresh();

        if (! $document instanceof KnowledgeDocument || $document->disk === null || $document->storage_path === null || $document->original_filename === null) {
            KnowledgeDocument::query()->whereKey($this->document->id)->update([
                'ingestion_status' => KnowledgeDocumentStatus::Failed,
                'quarantine_reason' => 'The ingestion record is missing its quarantined file metadata.',
                'processed_at' => now(),
            ]);

            return;
        }

        try {
            $guard->assertSafe($document->disk, $document->storage_path, $document->original_filename);

            $extension = strtolower(pathinfo($document->original_filename, PATHINFO_EXTENSION));
            $cleanPath = "knowledge/{$document->knowledge_source_id}/".Str::uuid()->toString().".{$extension}";

            if (! Storage::disk($document->disk)->move($document->storage_path, $cleanPath)) {
                throw KnowledgeExtractionException::unsafe('The scanned document could not be promoted from quarantine.');
            }

            $document->update(['storage_path' => $cleanPath]);
            $indexer->indexStoredDocument($document->fresh());
            $document->update([
                'ingestion_status' => KnowledgeDocumentStatus::Indexed,
                'processed_at' => now(),
            ]);
        } catch (KnowledgeExtractionException $exception) {
            $document->update([
                'ingestion_status' => KnowledgeDocumentStatus::Quarantined,
                'quarantine_reason' => Str::limit($exception->getMessage(), 255, ''),
                'processed_at' => now(),
            ]);
        } catch (Throwable $exception) {
            $document->update([
                'ingestion_status' => KnowledgeDocumentStatus::Failed,
                'quarantine_reason' => 'The ingestion worker failed; the file remains quarantined.',
            ]);

            throw $exception;
        }
    }

    public function uniqueId(): string
    {
        return 'knowledge-document:'.$this->document->id;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 30, 60];
    }

    public function failed(?Throwable $exception): void
    {
        KnowledgeDocument::query()->whereKey($this->document->id)->where('ingestion_status', KnowledgeDocumentStatus::Scanning->value)->update([
            'ingestion_status' => KnowledgeDocumentStatus::Failed,
            'quarantine_reason' => 'The ingestion worker failed; the file remains quarantined.',
            'processed_at' => now(),
        ]);
    }
}
