<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A governed knowledge (RAG) source: an approved collection of documents MAAC
 * indexes and retrieves from on behalf of an agent's knowledge-retrieval tool.
 * A source carries a sensitivity classification, the environments it is
 * available in, and freshness metadata (document/chunk counts + last indexed
 * time). A sensitive source (or one explicitly flagged) starts as a draft and is
 * gated behind an ingestion approval before the runtime may retrieve from it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('knowledge_sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft');
            $table->string('sensitivity');
            $table->boolean('requires_approval')->default(false);
            $table->json('environments');
            $table->unsignedInteger('document_count')->default(0);
            $table->unsignedInteger('chunk_count')->default(0);
            $table->timestamp('last_indexed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index('application_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_sources');
    }
};
