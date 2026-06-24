<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the knowledge-retrieval (RAG) execution config to tool contracts.
 * `knowledge_source_id` maps a knowledge-mode tool contract to the governed
 * source it retrieves from; `knowledge_config` holds the retrieval policy (the
 * number of chunks to return and the minimum relevance score).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tool_contracts', function (Blueprint $table): void {
            $table->foreignUuid('knowledge_source_id')->nullable()->after('redaction')->constrained('knowledge_sources')->nullOnDelete();
            $table->json('knowledge_config')->nullable()->after('knowledge_source_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tool_contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('knowledge_source_id');
            $table->dropColumn('knowledge_config');
        });
    }
};
