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
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->foreignUuid('agent_version_id')->nullable()->after('agent_id')->constrained('agent_versions')->nullOnDelete();
            $table->foreignId('initiated_by')->nullable()->after('application_id')->constrained('users')->nullOnDelete();
            $table->string('policy_version')->default('1.0.0')->after('correlation_id');
            $table->boolean('is_test')->default(false)->after('policy_version');
            $table->json('execution_snapshot')->nullable()->after('is_test');

            $table->index(['application_id', 'is_test', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->dropIndex(['application_id', 'is_test', 'created_at']);
            $table->dropConstrainedForeignId('agent_version_id');
            $table->dropConstrainedForeignId('initiated_by');
            $table->dropColumn(['policy_version', 'is_test', 'execution_snapshot']);
        });
    }
};
