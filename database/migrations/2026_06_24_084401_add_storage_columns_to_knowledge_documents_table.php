<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records where a knowledge document's source file lives when it was uploaded
 * (rather than pasted). The indexing pipeline reads the file from storage to
 * extract its text, so these columns are the source of truth for uploaded
 * documents; pasted documents leave them null.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->string('disk')->nullable()->after('body');
            $table->string('storage_path')->nullable()->after('disk');
            $table->string('original_filename')->nullable()->after('storage_path');
            $table->string('mime_type')->nullable()->after('original_filename');
            $table->unsignedBigInteger('file_size')->nullable()->after('mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->dropColumn(['disk', 'storage_path', 'original_filename', 'mime_type', 'file_size']);
        });
    }
};
