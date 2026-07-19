<?php

use App\Enums\KnowledgeDocumentStatus;
use App\Jobs\ProcessKnowledgeDocument;
use App\Models\KnowledgeSource;
use App\Support\Runtime\Knowledge\DocumentUploadGuard;
use App\Support\Runtime\Knowledge\KnowledgeExtractionException;
use App\Support\Runtime\Knowledge\KnowledgeIndexer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
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

    $tmp = sys_get_temp_dir().'/maacc_'.uniqid().'.docx';
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

test('a corrupt upload is retained in quarantine without being indexed', function () {
    $this->actingAs($this->owner)
        ->post(ingestUrl(), [
            'title' => 'Corrupt PDF',
            'document' => UploadedFile::fake()->createWithContent('broken.pdf', 'not a real pdf at all'),
        ])
        ->assertRedirect();

    $document = $this->source->documents()->firstOrFail();

    expect($document->ingestion_status)->toBe(KnowledgeDocumentStatus::Quarantined)
        ->and($document->indexed_at)->toBeNull()
        ->and($document->quarantine_reason)->not->toBeNull()
        ->and(Storage::disk('local')->exists((string) $document->storage_path))->toBeTrue();
});

test('an upload is accepted into quarantine and dispatched to the isolated queue', function () {
    Queue::fake();

    $this->actingAs($this->owner)
        ->post(ingestUrl(), [
            'title' => 'Queued document',
            'document' => UploadedFile::fake()->createWithContent('queued.txt', 'Safe queued content.'),
        ])
        ->assertRedirect();

    $document = $this->source->documents()->firstOrFail();

    expect($document->ingestion_status)->toBe(KnowledgeDocumentStatus::Pending)
        ->and($document->storage_path)->toStartWith('knowledge-quarantine/')
        ->and($document->initiated_by)->toBe($this->owner->id)
        ->and($document->correlation_id)->not->toBeNull();

    Queue::assertPushed(ProcessKnowledgeDocument::class, fn (ProcessKnowledgeDocument $job): bool => $job->document->is($document) && $job->queue === 'ingestion');
});

test('a filename and content mismatch remains quarantined', function () {
    $this->actingAs($this->owner)
        ->post(ingestUrl(), [
            'title' => 'Disguised binary',
            'document' => UploadedFile::fake()->createWithContent('disguised.pdf', 'plain text disguised as a PDF'),
        ])
        ->assertRedirect();

    expect($this->source->documents()->firstOrFail()->ingestion_status)
        ->toBe(KnowledgeDocumentStatus::Quarantined);
});

test('the malware test signature remains quarantined', function () {
    $eicar = 'X5O!P%@AP[4\\PZX54(P^)7CC)7}$EICAR-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*';

    $this->actingAs($this->owner)
        ->post(ingestUrl(), [
            'title' => 'Unsafe upload',
            'document' => UploadedFile::fake()->createWithContent('unsafe.txt', $eicar),
        ])
        ->assertRedirect();

    $document = $this->source->documents()->firstOrFail();

    expect($document->ingestion_status)->toBe(KnowledgeDocumentStatus::Quarantined)
        ->and($document->body)->toBe('')
        ->and($document->quarantine_reason)->toContain('malware');
});

test('a compressed document that exceeds expansion limits remains quarantined', function () {
    config(['maacc.runtime.knowledge.upload.max_decompressed_kb' => 1]);

    $phpWord = new PhpWord;
    $phpWord->addSection()->addText(str_repeat('highly-compressible-content ', 10000));
    $tmp = sys_get_temp_dir().'/maacc_bomb_'.uniqid().'.docx';
    WordIOFactory::createWriter($phpWord, 'Word2007')->save($tmp);
    $bytes = (string) file_get_contents($tmp);
    unlink($tmp);

    $this->actingAs($this->owner)
        ->post(ingestUrl(), [
            'title' => 'Expansion bomb',
            'document' => UploadedFile::fake()->createWithContent('bomb.docx', $bytes),
        ])
        ->assertRedirect();

    $document = $this->source->documents()->firstOrFail();

    expect($document->ingestion_status)->toBe(KnowledgeDocumentStatus::Quarantined)
        ->and($document->quarantine_reason)->toContain('decompression');
});

test('the ingestion worker declares isolated bounded retry behavior', function () {
    $document = $this->source->documents()->create([
        'title' => 'Worker metadata',
        'body' => '',
        'checksum' => '',
        'ingestion_status' => KnowledgeDocumentStatus::Pending,
    ]);
    $job = (new ProcessKnowledgeDocument($document))->onQueue('ingestion');

    expect($job->queue)->toBe('ingestion')
        ->and($job->tries)->toBe(3)
        ->and($job->timeout)->toBeLessThan(config('queue.connections.database.retry_after'))
        ->and($job->backoff())->toBe([10, 30, 60])
        ->and($job->uniqueId())->toContain($document->id);
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

test('the upload guard rejects bounded file and extracted-text edge cases', function () {
    $guard = app(DocumentUploadGuard::class);
    $store = function (string $name, string $contents) use ($guard): void {
        Storage::disk('local')->put("guards/{$name}", $contents);
        $guard->assertSafe('local', "guards/{$name}", $name);
    };

    expect(fn () => $store('blocked.exe', 'content'))
        ->toThrow(KnowledgeExtractionException::class, 'not allowed')
        ->and(fn () => $store('empty.txt', ''))
        ->toThrow(KnowledgeExtractionException::class, 'empty or unreadable');

    config(['maacc.runtime.knowledge.upload.max_kb' => 1]);
    expect(fn () => $store('large.txt', str_repeat('x', 2048)))
        ->toThrow(KnowledgeExtractionException::class, 'size limit');

    config(['maacc.runtime.knowledge.upload.max_kb' => 10240]);
    expect(fn () => $store('binary.txt', "text\0binary"))
        ->toThrow(KnowledgeExtractionException::class, 'binary content');

    config(['maacc.runtime.knowledge.upload.max_text_characters' => 5]);
    expect(fn () => $guard->assertExtractedText('sixsix'))
        ->toThrow(KnowledgeExtractionException::class, 'character safety limit');
});

test('the upload guard enforces PDF and DOCX structure limits', function () {
    $guard = app(DocumentUploadGuard::class);
    $magic = new ReflectionMethod($guard, 'assertMagic');
    $expansion = new ReflectionMethod($guard, 'assertExpansionLimits');

    expect(fn () => $magic->invoke($guard, 'pdf', 'not-pdf'))
        ->toThrow(KnowledgeExtractionException::class, 'PDF signature')
        ->and(fn () => $magic->invoke($guard, 'docx', 'not-docx'))
        ->toThrow(KnowledgeExtractionException::class, 'DOCX archive signature');

    config(['maacc.runtime.knowledge.upload.max_pdf_pages' => 1]);
    expect(fn () => $expansion->invoke($guard, 'pdf', "%PDF-1.4\n/Type /Page\n/Type /Page"))
        ->toThrow(KnowledgeExtractionException::class, 'page safety limit');

    $temporary = tempnam(sys_get_temp_dir(), 'maacc_guard_zip_');
    $archive = new ZipArchive;
    $archive->open($temporary, ZipArchive::OVERWRITE);
    $archive->addFromString('unrelated.txt', 'content');
    $archive->close();
    $bytes = (string) file_get_contents($temporary);
    unlink($temporary);

    expect(fn () => $expansion->invoke($guard, 'docx', $bytes))
        ->toThrow(KnowledgeExtractionException::class, 'missing required document parts');

    $temporary = tempnam(sys_get_temp_dir(), 'maacc_guard_zip_');
    $archive = new ZipArchive;
    $archive->open($temporary, ZipArchive::OVERWRITE);
    $archive->addFromString('[Content_Types].xml', '<Types/>');
    $archive->addFromString('word/document.xml', '<document/>');
    $archive->addFromString('extra.txt', 'content');
    $archive->close();
    $bytes = (string) file_get_contents($temporary);
    unlink($temporary);

    config(['maacc.runtime.knowledge.upload.max_archive_entries' => 2]);
    expect(fn () => $expansion->invoke($guard, 'docx', $bytes))
        ->toThrow(KnowledgeExtractionException::class, 'entry safety limit');
});

test('the upload guard fails closed and handles scanner outcomes', function () {
    $guard = app(DocumentUploadGuard::class);
    $scan = new ReflectionMethod($guard, 'assertMalwareFree');

    app()->detectEnvironment(fn (): string => 'production');
    config(['maacc.runtime.knowledge.upload.malware_scanner_binary' => null]);
    expect(fn () => $scan->invoke($guard, 'safe content'))
        ->toThrow(KnowledgeExtractionException::class, 'scanning is unavailable');

    app()->detectEnvironment(fn (): string => 'testing');
    $scanner = tempnam(sys_get_temp_dir(), 'maacc_scanner_');
    file_put_contents($scanner, "#!/bin/sh\nexit 1\n");
    chmod($scanner, 0700);

    try {
        config(['maacc.runtime.knowledge.upload.malware_scanner_binary' => $scanner]);
        expect(fn () => $scan->invoke($guard, 'safe content'))
            ->toThrow(KnowledgeExtractionException::class, 'malware scanning');
    } finally {
        unlink($scanner);
    }

    config(['maacc.runtime.knowledge.upload.malware_scanner_binary' => '/path/does/not/exist']);
    expect(fn () => $scan->invoke($guard, 'safe content'))
        ->toThrow(KnowledgeExtractionException::class, 'scanning failed');

    $scanner = tempnam(sys_get_temp_dir(), 'maacc_scanner_');
    file_put_contents($scanner, "#!/bin/sh\nexit 0\n");
    chmod($scanner, 0700);

    try {
        config(['maacc.runtime.knowledge.upload.malware_scanner_binary' => $scanner]);
        $scan->invoke($guard, 'safe content');
    } finally {
        unlink($scanner);
    }

    expect(true)->toBeTrue();
});

test('the upload guard fails closed when temporary files or scanner startup are unavailable', function () {
    $temporaryFailure = new DocumentUploadGuard(fn (string $prefix): false => false);
    $expansion = new ReflectionMethod($temporaryFailure, 'assertExpansionLimits');
    $scan = new ReflectionMethod($temporaryFailure, 'assertMalwareFree');

    expect(fn () => $expansion->invoke($temporaryFailure, 'docx', "PK\x03\x04content"))
        ->toThrow(KnowledgeExtractionException::class, 'temporary file')
        ->and(function () use ($scan, $temporaryFailure): void {
            config(['maacc.runtime.knowledge.upload.malware_scanner_binary' => '/scanner']);
            $scan->invoke($temporaryFailure, 'safe content');
        })->toThrow(KnowledgeExtractionException::class, 'temporary file');

    $scannerFailure = new DocumentUploadGuard(
        null,
        fn (array $command): never => throw new RuntimeException('scanner startup failed'),
    );
    $scan = new ReflectionMethod($scannerFailure, 'assertMalwareFree');
    config(['maacc.runtime.knowledge.upload.malware_scanner_binary' => '/scanner']);

    expect(fn () => $scan->invoke($scannerFailure, 'safe content'))
        ->toThrow(KnowledgeExtractionException::class, 'scanning failed');
});

test('the ingestion worker handles duplicate and incomplete records deterministically', function () {
    $indexed = $this->source->documents()->create([
        'title' => 'Already indexed',
        'body' => 'done',
        'checksum' => hash('sha256', 'done'),
        'ingestion_status' => KnowledgeDocumentStatus::Indexed,
    ]);
    app()->call([new ProcessKnowledgeDocument($indexed), 'handle']);
    expect($indexed->fresh()->ingestion_status)->toBe(KnowledgeDocumentStatus::Indexed);

    $incomplete = $this->source->documents()->create([
        'title' => 'Incomplete',
        'body' => '',
        'checksum' => '',
        'ingestion_status' => KnowledgeDocumentStatus::Pending,
    ]);
    app()->call([new ProcessKnowledgeDocument($incomplete), 'handle']);

    expect($incomplete->fresh()->ingestion_status)->toBe(KnowledgeDocumentStatus::Failed)
        ->and($incomplete->fresh()->quarantine_reason)->toContain('missing');
});

test('the ingestion worker quarantines a file that cannot be promoted', function () {
    $document = $this->source->documents()->create([
        'title' => 'Missing quarantine object',
        'body' => '',
        'checksum' => '',
        'disk' => 'local',
        'storage_path' => 'knowledge-quarantine/missing.txt',
        'original_filename' => 'missing.txt',
        'ingestion_status' => KnowledgeDocumentStatus::Pending,
    ]);
    $guard = Mockery::mock(DocumentUploadGuard::class);
    $guard->shouldReceive('assertSafe')->once();

    (new ProcessKnowledgeDocument($document))->handle($guard, app(KnowledgeIndexer::class));

    expect($document->fresh()->ingestion_status)->toBe(KnowledgeDocumentStatus::Quarantined)
        ->and($document->fresh()->quarantine_reason)->toContain('promoted');
});

test('the ingestion worker records infrastructure failures and its failure callback repairs scanning state', function () {
    Storage::disk('local')->put('knowledge-quarantine/crash.txt', 'safe content');
    $document = $this->source->documents()->create([
        'title' => 'Crash',
        'body' => '',
        'checksum' => '',
        'disk' => 'local',
        'storage_path' => 'knowledge-quarantine/crash.txt',
        'original_filename' => 'crash.txt',
        'ingestion_status' => KnowledgeDocumentStatus::Pending,
    ]);
    $guard = Mockery::mock(DocumentUploadGuard::class);
    $guard->shouldReceive('assertSafe')->once();
    $indexer = Mockery::mock(KnowledgeIndexer::class);
    $indexer->shouldReceive('indexStoredDocument')->once()->andThrow(new RuntimeException('index unavailable'));
    $job = new ProcessKnowledgeDocument($document);

    expect(fn () => $job->handle($guard, $indexer))->toThrow(RuntimeException::class, 'index unavailable');
    expect($document->fresh()->ingestion_status)->toBe(KnowledgeDocumentStatus::Failed);

    $document->update(['ingestion_status' => KnowledgeDocumentStatus::Scanning]);
    $job->failed(null);
    expect($document->fresh()->ingestion_status)->toBe(KnowledgeDocumentStatus::Failed)
        ->and($document->fresh()->processed_at)->not->toBeNull();
});
