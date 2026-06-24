<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A retrievable chunk of a knowledge document produced by the indexing pipeline.
 * The retriever scores chunks against a query and maps the top matches back to
 * their document for citation. `tokens` holds the normalized token list used by
 * the deterministic lexical retriever; `knowledge_source_id` is denormalized so
 * a retrieval can scope to a source in one query.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('knowledge_chunks', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('knowledge_document_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('knowledge_source_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('ordinal')->default(0);
            $table->text('content');
            $table->json('tokens');
            $table->unsignedInteger('token_count')->default(0);
            $table->timestamps();

            $table->index('knowledge_source_id');
            $table->index('knowledge_document_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_chunks');
    }
};
