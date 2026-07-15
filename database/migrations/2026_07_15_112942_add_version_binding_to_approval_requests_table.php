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
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->string('subject_version_hash')->nullable()->after('subject_id');
            $table->string('pending_key')->nullable()->unique()->after('subject_version_hash');
            $table->index(['subject_type', 'subject_id', 'subject_version_hash'], 'approval_subject_version_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('approval_requests', function (Blueprint $table) {
            $table->dropIndex('approval_subject_version_index');
            $table->dropUnique(['pending_key']);
            $table->dropColumn(['subject_version_hash', 'pending_key']);
        });
    }
};
