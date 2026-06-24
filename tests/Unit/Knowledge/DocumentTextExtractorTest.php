<?php

use App\Support\Runtime\Knowledge\DocumentTextExtractor;
use App\Support\Runtime\Knowledge\KnowledgeExtractionException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;
use Tests\TestCase;

// Booting the application (no database) gives us the Storage facade the
// extractor reads from, plus the bundled PDF/Word parsers.
uses(TestCase::class);

beforeEach(function () {
    Storage::fake('local');
    $this->extractor = new DocumentTextExtractor;
});

it('reads plain-text formats verbatim from storage', function (string $extension) {
    Storage::disk('local')->put("docs/sample.{$extension}", "First passage.\n\nSecond passage.");

    expect($this->extractor->extract('local', "docs/sample.{$extension}", $extension))
        ->toBe("First passage.\n\nSecond passage.");
})->with(['txt', 'md', 'markdown', 'csv', 'TXT']);

it('extracts the text layer from a PDF', function () {
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/knowledge/policy.pdf'));
    Storage::disk('local')->put('docs/policy.pdf', $bytes);

    expect($this->extractor->extract('local', 'docs/policy.pdf', 'pdf'))
        ->toContain('Berth allocation');
});

it('extracts paragraph text from a Word document', function () {
    $phpWord = new PhpWord;
    $section = $phpWord->addSection();
    $run = $section->addTextRun();
    $run->addText('Vessel compliance ');
    $run->addText('manual.');
    $section->addText('Second indexed paragraph in the document.');
    $section->addTextBreak();

    $absolute = Storage::disk('local')->path('docs/manual.docx');
    File::ensureDirectoryExists(dirname($absolute));
    WordIOFactory::createWriter($phpWord, 'Word2007')->save($absolute);

    $text = $this->extractor->extract('local', 'docs/manual.docx', 'docx');

    expect($text)->toContain('Vessel compliance manual.')
        ->and($text)->toContain('Second indexed paragraph');
});

it('rejects an unsupported extension', function () {
    Storage::disk('local')->put('docs/image.png', 'binary');

    expect(fn () => $this->extractor->extract('local', 'docs/image.png', 'png'))
        ->toThrow(KnowledgeExtractionException::class);
});

it('fails when a plain-text file is missing from storage', function () {
    expect(fn () => $this->extractor->extract('local', 'docs/missing.txt', 'txt'))
        ->toThrow(KnowledgeExtractionException::class);
});

it('fails when a Word file is missing from storage', function () {
    expect(fn () => $this->extractor->extract('local', 'docs/missing.docx', 'docx'))
        ->toThrow(KnowledgeExtractionException::class);
});

it('fails on a corrupt PDF', function () {
    Storage::disk('local')->put('docs/bad.pdf', 'this is plainly not a PDF document');

    expect(fn () => $this->extractor->extract('local', 'docs/bad.pdf', 'pdf'))
        ->toThrow(KnowledgeExtractionException::class);
});

it('fails on a corrupt Word document', function () {
    Storage::disk('local')->put('docs/bad.docx', 'this is plainly not a DOCX archive');

    expect(fn () => $this->extractor->extract('local', 'docs/bad.docx', 'docx'))
        ->toThrow(KnowledgeExtractionException::class);
});
