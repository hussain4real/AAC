<?php

namespace App\Support\Runtime\Knowledge;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;
use Throwable;
use ZipArchive;

/**
 * Fail-closed security gate for files held in the knowledge quarantine area.
 * Validation uses detected content and magic bytes rather than trusting the
 * supplied filename or Content-Type, and bounds parser-expansion work before a
 * file can be promoted to the indexed document area.
 */
class DocumentUploadGuard
{
    /** @var array<string, list<string>> */
    private const MIME_TYPES = [
        'txt' => ['text/plain', 'application/octet-stream'],
        'md' => ['text/plain', 'application/octet-stream'],
        'markdown' => ['text/plain', 'application/octet-stream'],
        'csv' => ['text/plain', 'text/csv', 'application/csv', 'application/octet-stream'],
        'pdf' => ['application/pdf'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
    ];

    public function assertSafe(string $disk, string $path, string $originalFilename): void
    {
        $extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));
        $allowed = (array) config('maacc.runtime.knowledge.upload.allowed_extensions', []);

        if (! in_array($extension, $allowed, true)) {
            throw KnowledgeExtractionException::unsafe('The uploaded document type is not allowed.');
        }

        $contents = Storage::disk($disk)->get($path);

        if (! is_string($contents) || $contents === '') {
            throw KnowledgeExtractionException::unsafe('The uploaded document is empty or unreadable.');
        }

        $maxBytes = max(1, (int) config('maacc.runtime.knowledge.upload.max_kb', 10240)) * 1024;

        if (strlen($contents) > $maxBytes) {
            throw KnowledgeExtractionException::unsafe('The uploaded document exceeds the configured size limit.');
        }

        $detectedMime = (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents) ?: 'application/octet-stream';

        if (! in_array($detectedMime, self::MIME_TYPES[$extension] ?? [], true)) {
            throw KnowledgeExtractionException::unsafe("The document content does not match its .{$extension} filename.");
        }

        $this->assertMagic($extension, $contents);
        $this->assertExpansionLimits($extension, $contents);
        $this->assertMalwareFree($contents);
    }

    public function assertExtractedText(string $text): void
    {
        $max = max(1, (int) config('maacc.runtime.knowledge.upload.max_text_characters', 1000000));

        if (mb_strlen($text) > $max) {
            throw KnowledgeExtractionException::unsafe("The extracted document exceeds the {$max}-character safety limit.");
        }
    }

    private function assertMagic(string $extension, string $contents): void
    {
        if ($extension === 'pdf' && ! str_starts_with($contents, '%PDF-')) {
            throw KnowledgeExtractionException::unsafe('The document does not contain a valid PDF signature.');
        }

        if ($extension === 'docx' && ! str_starts_with($contents, "PK\x03\x04")) {
            throw KnowledgeExtractionException::unsafe('The document does not contain a valid DOCX archive signature.');
        }

        if (in_array($extension, ['txt', 'md', 'markdown', 'csv'], true) && str_contains($contents, "\0")) {
            throw KnowledgeExtractionException::unsafe('The text document contains binary content.');
        }
    }

    private function assertExpansionLimits(string $extension, string $contents): void
    {
        if ($extension === 'pdf') {
            $pages = preg_match_all('/\/Type\s*\/Page\b/', $contents);
            $maxPages = max(1, (int) config('maacc.runtime.knowledge.upload.max_pdf_pages', 500));

            if ($pages > $maxPages) {
                throw KnowledgeExtractionException::unsafe("The PDF exceeds the {$maxPages}-page safety limit.");
            }

            return;
        }

        if ($extension !== 'docx') {
            return;
        }

        $temporary = tempnam(sys_get_temp_dir(), 'maacc_docx_');

        if ($temporary === false) {
            throw KnowledgeExtractionException::unsafe('A secure temporary file could not be created for document inspection.');
        }

        file_put_contents($temporary, $contents);
        $archive = new ZipArchive;

        try {
            if ($archive->open($temporary) !== true || $archive->locateName('[Content_Types].xml') === false || $archive->locateName('word/document.xml') === false) {
                throw KnowledgeExtractionException::unsafe('The DOCX package is missing required document parts.');
            }

            $maxEntries = max(1, (int) config('maacc.runtime.knowledge.upload.max_archive_entries', 500));

            if ($archive->numFiles > $maxEntries) {
                throw KnowledgeExtractionException::unsafe("The DOCX archive exceeds the {$maxEntries}-entry safety limit.");
            }

            $compressed = 0;
            $uncompressed = 0;

            for ($index = 0; $index < $archive->numFiles; $index++) {
                $stat = $archive->statIndex($index);
                $compressed += (int) ($stat['comp_size'] ?? 0);
                $uncompressed += (int) ($stat['size'] ?? 0);
            }

            $maxExpandedBytes = max(1, (int) config('maacc.runtime.knowledge.upload.max_decompressed_kb', 51200)) * 1024;
            $maxRatio = max(1, (int) config('maacc.runtime.knowledge.upload.max_compression_ratio', 100));

            if ($uncompressed > $maxExpandedBytes || ($compressed > 0 && $uncompressed / $compressed > $maxRatio)) {
                throw KnowledgeExtractionException::unsafe('The DOCX archive exceeds the configured decompression safety limits.');
            }
        } finally {
            $archive->close();
            @unlink($temporary);
        }
    }

    private function assertMalwareFree(string $contents): void
    {
        if (str_contains($contents, 'EICAR-STANDARD-ANTIVIRUS-TEST-FILE')) {
            throw KnowledgeExtractionException::unsafe('The document was quarantined by malware scanning.');
        }

        $binary = config('maacc.runtime.knowledge.upload.malware_scanner_binary');

        if (! is_string($binary) || trim($binary) === '') {
            if (App::environment('production')) {
                throw KnowledgeExtractionException::unsafe('Document scanning is unavailable; production ingestion fails closed.');
            }

            return;
        }

        $temporary = tempnam(sys_get_temp_dir(), 'maacc_scan_');

        if ($temporary === false) {
            throw KnowledgeExtractionException::unsafe('A secure temporary file could not be created for malware scanning.');
        }

        file_put_contents($temporary, $contents);

        try {
            $process = new Process([$binary, '--no-summary', $temporary]);
            $process->setTimeout(max(1, (int) config('maacc.runtime.knowledge.upload.scanner_timeout_seconds', 60)));
            $process->run();

            if ($process->getExitCode() === 1) {
                throw KnowledgeExtractionException::unsafe('The document was quarantined by malware scanning.');
            }

            if (! $process->isSuccessful()) {
                throw KnowledgeExtractionException::unsafe('Document scanning failed; ingestion remains quarantined.');
            }
        } catch (KnowledgeExtractionException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw KnowledgeExtractionException::unsafe('Document scanning failed; ingestion remains quarantined.');
        } finally {
            @unlink($temporary);
        }
    }
}
