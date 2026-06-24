<?php

namespace App\Support\Runtime\Knowledge;

use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\Element\AbstractContainer;
use PhpOffice\PhpWord\Element\AbstractElement;
use PhpOffice\PhpWord\Element\Text as TextElement;
use PhpOffice\PhpWord\Element\TextRun;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use Smalot\PdfParser\Parser as PdfParser;
use Throwable;

/**
 * Extracts plain text from an uploaded document held in storage so the indexing
 * pipeline can chunk it. Plain-text formats (txt/md/markdown/csv) are read
 * verbatim; PDFs are parsed with smalot/pdfparser and Word documents with
 * phpoffice/phpword. Anything else — or a corrupt/unreadable file — raises a
 * KnowledgeExtractionException the caller turns into a clear error.
 */
class DocumentTextExtractor
{
    /**
     * Extract the document's text from storage, keyed on its file extension.
     */
    public function extract(string $disk, string $path, string $extension): string
    {
        return match (strtolower($extension)) {
            'txt', 'md', 'markdown', 'csv' => $this->readText($disk, $path),
            'pdf' => $this->extractPdf($disk, $path),
            'docx' => $this->extractDocx($disk, $path),
            default => throw KnowledgeExtractionException::unsupportedExtension(strtolower($extension)),
        };
    }

    /**
     * Read a plain-text file's contents verbatim.
     */
    private function readText(string $disk, string $path): string
    {
        $contents = Storage::disk($disk)->get($path);

        if ($contents === null) {
            throw KnowledgeExtractionException::unreadable($path);
        }

        return $contents;
    }

    /**
     * Extract the text layer from a PDF document.
     */
    private function extractPdf(string $disk, string $path): string
    {
        $contents = $this->readText($disk, $path);

        try {
            return (new PdfParser)->parseContent($contents)->getText();
        } catch (Throwable $exception) {
            throw KnowledgeExtractionException::extractionFailed('pdf', $exception);
        }
    }

    /**
     * Extract the visible text from a Word (.docx) document.
     */
    private function extractDocx(string $disk, string $path): string
    {
        /** @var FilesystemAdapter $filesystem */
        $filesystem = Storage::disk($disk);

        if (! $filesystem->exists($path)) {
            throw KnowledgeExtractionException::unreadable($path);
        }

        try {
            $document = WordIOFactory::load($filesystem->path($path));
        } catch (Throwable $exception) {
            throw KnowledgeExtractionException::extractionFailed('docx', $exception);
        }

        $sections = array_map(
            fn (AbstractContainer $section): string => $this->elementText($section),
            $document->getSections(),
        );

        return trim(implode("\n\n", array_filter($sections, fn (string $text): bool => trim($text) !== '')));
    }

    /**
     * Recursively collect the text of a Word element and its descendants. The
     * inline runs of a paragraph are concatenated, while block-level children
     * (paragraphs, table cells, …) are separated by blank lines so the indexer
     * chunks them as distinct passages.
     */
    private function elementText(AbstractElement $element): string
    {
        if ($element instanceof TextElement) {
            return $element->getText();
        }

        if ($element instanceof TextRun) {
            return implode('', array_map(
                fn (AbstractElement $child): string => $this->elementText($child),
                $element->getElements(),
            ));
        }

        if ($element instanceof AbstractContainer) {
            $parts = array_map(
                fn (AbstractElement $child): string => $this->elementText($child),
                $element->getElements(),
            );

            return implode("\n\n", array_filter($parts, fn (string $text): bool => trim($text) !== ''));
        }

        return '';
    }
}
