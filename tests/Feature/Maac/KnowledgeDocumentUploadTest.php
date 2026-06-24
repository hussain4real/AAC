<?php

use App\Models\KnowledgeSource;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory as WordIOFactory;
use PhpOffice\PhpWord\PhpWord;

beforeEach(function () {
    Storage::fake('local');
    [$this->owner, $this->team] = ownerAndTeam();
    $this->source = KnowledgeSource::factory()->for($this->team)->create();
});

/** Build the document-ingest endpoint for the current source. */
function ingestUrl(): string
{
    return route('knowledge-sources.documents.store', [
        'current_team' => test()->team->slug,
        'knowledgeSource' => test()->source->slug,
    ]);
}

/** Build a real .docx upload with two paragraphs of known text. */
function fakeDocxUpload(string $name = 'manual.docx'): UploadedFile
{
    $phpWord = new PhpWord;
    $section = $phpWord->addSection();
    $section->addText('Vessel compliance manual first paragraph.');
    $section->addText('Vessel compliance manual second paragraph.');

    $tmp = sys_get_temp_dir().'/maac_'.uniqid().'.docx';
    WordIOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
    $bytes = (string) file_get_contents($tmp);
    unlink($tmp);

    return UploadedFile::fake()->createWithContent($name, $bytes);
}

test('a text file upload is stored and indexed from storage', function () {
    $file = UploadedFile::fake()->createWithContent(
        'berth.txt',
        "Berth allocation prioritizes vessels by arrival window.\n\nDelayed vessels are reassigned.",
    );

    $this->actingAs($this->owner)
        ->post(ingestUrl(), [
            'title' => 'Berth Policy',
            'document' => $file,
            'metadata' => ['author' => 'Ops'],
        ])
        ->assertRedirect();

    $document = $this->source->documents()->first();

    expect($document)->not->toBeNull()
        ->and($document->isUploaded())->toBeTrue()
        ->and($document->original_filename)->toBe('berth.txt')
        ->and($document->file_size)->toBeGreaterThan(0)
        ->and($document->body)->toContain('Berth allocation')
        ->and($document->chunks()->count())->toBe(2)
        ->and($document->metadata)->toBe(['author' => 'Ops']);

    Storage::disk('local')->assertExists($document->storage_path);

    $fresh = $this->source->fresh();
    expect($fresh->document_count)->toBe(1)
        ->and($fresh->chunk_count)->toBe(2)
        ->and($fresh->last_indexed_at)->not->toBeNull();
});

test('a Word document upload is extracted and indexed', function () {
    $this->actingAs($this->owner)
        ->post(ingestUrl(), ['title' => 'Compliance Manual', 'document' => fakeDocxUpload()])
        ->assertRedirect();

    $document = $this->source->documents()->first();

    expect($document->isUploaded())->toBeTrue()
        ->and($document->original_filename)->toBe('manual.docx')
        ->and($document->body)->toContain('Vessel compliance manual first paragraph.')
        ->and($document->chunks()->count())->toBe(2);
});

test('a PDF upload is extracted and indexed', function () {
    $bytes = (string) file_get_contents(base_path('tests/Fixtures/knowledge/policy.pdf'));

    $this->actingAs($this->owner)
        ->post(ingestUrl(), [
            'title' => 'Berth PDF',
            'document' => UploadedFile::fake()->createWithContent('policy.pdf', $bytes),
        ])
        ->assertRedirect();

    $document = $this->source->documents()->first();

    expect($document->isUploaded())->toBeTrue()
        ->and($document->body)->toContain('Berth allocation')
        ->and($document->chunks()->count())->toBeGreaterThan(0);
});

test('a pasted body still ingests without a file', function () {
    $this->actingAs($this->owner)
        ->post(ingestUrl(), [
            'title' => 'Pasted Doc',
            'body' => "Paragraph one.\n\nParagraph two.",
        ])
        ->assertRedirect();

    $document = $this->source->documents()->first();

    expect($document->isUploaded())->toBeFalse()
        ->and($document->original_filename)->toBeNull()
        ->and($document->chunks()->count())->toBe(2);
});

test('ingestion requires either a body or a file', function () {
    $this->actingAs($this->owner)
        ->post(ingestUrl(), ['title' => 'Empty Doc'])
        ->assertSessionHasErrors(['body', 'document']);

    expect($this->source->documents()->count())->toBe(0);
});

test('an upload with a disallowed extension is rejected', function () {
    $this->actingAs($this->owner)
        ->post(ingestUrl(), [
            'title' => 'Bad Type',
            'document' => UploadedFile::fake()->createWithContent('image.png', 'binary'),
        ])
        ->assertSessionHasErrors('document');

    expect($this->source->documents()->count())->toBe(0);
});

test('an upload larger than the size cap is rejected', function () {
    $this->actingAs($this->owner)
        ->post(ingestUrl(), [
            'title' => 'Too Big',
            'document' => UploadedFile::fake()->create('huge.txt', 11000),
        ])
        ->assertSessionHasErrors('document');

    expect($this->source->documents()->count())->toBe(0);
});

test('a corrupt upload fails validation and cleans up the stored file', function () {
    $this->actingAs($this->owner)
        ->post(ingestUrl(), [
            'title' => 'Corrupt PDF',
            'document' => UploadedFile::fake()->createWithContent('broken.pdf', 'not a real pdf at all'),
        ])
        ->assertSessionHasErrors('document');

    expect($this->source->documents()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles("knowledge/{$this->source->id}"))->toBe([]);
});

test('a plain member cannot ingest a document', function () {
    $member = teamMember($this->team);

    $this->actingAs($member)
        ->post(ingestUrl(), [
            'title' => 'Blocked',
            'document' => UploadedFile::fake()->createWithContent('note.txt', 'content'),
        ])
        ->assertForbidden();
});

test('re-indexing re-reads an uploaded file from storage', function () {
    $this->actingAs($this->owner)
        ->post(ingestUrl(), [
            'title' => 'Living Doc',
            'document' => UploadedFile::fake()->createWithContent('doc.txt', 'Original single paragraph.'),
        ])
        ->assertRedirect();

    $document = $this->source->documents()->first();
    expect($document->chunks()->count())->toBe(1);

    // Storage is the source of truth: replace the file, then re-index.
    Storage::disk('local')->put($document->storage_path, "Revised first paragraph.\n\nA brand new second paragraph.");

    $this->actingAs($this->owner)
        ->post(route('knowledge-sources.reindex', [
            'current_team' => $this->team->slug,
            'knowledgeSource' => $this->source->slug,
        ]))
        ->assertRedirect();

    $document->refresh();
    expect($document->body)->toContain('brand new second paragraph')
        ->and($document->chunks()->count())->toBe(2);
});

test('removing an uploaded document deletes its stored file', function () {
    $this->actingAs($this->owner)
        ->post(ingestUrl(), [
            'title' => 'Disposable',
            'document' => UploadedFile::fake()->createWithContent('temp.txt', 'Some content.'),
        ])
        ->assertRedirect();

    $document = $this->source->documents()->first();
    $path = $document->storage_path;
    Storage::disk('local')->assertExists($path);

    $this->actingAs($this->owner)
        ->delete(route('knowledge-documents.destroy', [
            'current_team' => $this->team->slug,
            'knowledgeDocument' => $document->id,
        ]))
        ->assertRedirect();

    Storage::disk('local')->assertMissing($path);
    expect($this->source->fresh()->document_count)->toBe(0)
        ->and($this->source->fresh()->chunk_count)->toBe(0);
});
