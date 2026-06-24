<?php

namespace App\Support\Runtime\Knowledge;

use RuntimeException;
use Throwable;

/**
 * Raised when an uploaded document's text cannot be extracted from storage —
 * an unsupported extension, an unreadable/missing file, or a corrupt PDF/DOCX
 * the parser rejects. The console ingest path turns this into a validation
 * error so the operator sees why the upload failed.
 */
class KnowledgeExtractionException extends RuntimeException
{
    /**
     * The file's extension could not be handled by any extractor.
     */
    public static function unsupportedExtension(string $extension): self
    {
        return new self("Unsupported document type: .{$extension}.");
    }

    /**
     * The file could not be read from storage.
     */
    public static function unreadable(string $path): self
    {
        return new self("The document file could not be read from storage: {$path}.");
    }

    /**
     * The parser failed to extract text from the file's contents.
     */
    public static function extractionFailed(string $extension, Throwable $previous): self
    {
        return new self("Could not extract text from the {$extension} document.", 0, $previous);
    }
}
