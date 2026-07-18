<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->string('ingestion_status')->default('indexed')->after('file_size');
            $table->string('quarantine_reason')->nullable()->after('ingestion_status');
            $table->unsignedInteger('processing_attempts')->default(0)->after('quarantine_reason');
            $table->foreignId('initiated_by')->nullable()->after('processing_attempts')->constrained('users')->nullOnDelete();
            $table->uuid('correlation_id')->nullable()->after('initiated_by');
            $table->timestamp('processed_at')->nullable()->after('correlation_id');

            $table->index(['ingestion_status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledge_documents', function (Blueprint $table): void {
            $table->dropIndex(['ingestion_status', 'created_at']);
            $table->dropConstrainedForeignId('initiated_by');
            $table->dropColumn([
                'ingestion_status',
                'quarantine_reason',
                'processing_attempts',
                'correlation_id',
                'processed_at',
            ]);
        });
    }
};
