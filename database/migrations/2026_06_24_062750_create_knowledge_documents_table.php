<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A single ingested document within a knowledge source. It carries the source
 * attribution (title + uri) and freshness metadata used to build citations, the
 * raw body the indexer chunks, and a checksum used to detect when a re-ingest
 * has changed the content.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('knowledge_documents', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('knowledge_source_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('uri')->nullable();
            $table->longText('body');
            $table->string('checksum', 64);
            $table->json('metadata')->nullable();
            $table->timestamp('indexed_at')->nullable();
            $table->timestamps();

            $table->index('knowledge_source_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_documents');
    }
};
