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
        Schema::table('project_members', function (Blueprint $table) {
            $table->foreignId('granted_by_user_id')->nullable()->after('maacc_role')->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by_user_id')->nullable()->after('granted_by_user_id')->constrained('users')->nullOnDelete();
            $table->foreignId('certified_by_user_id')->nullable()->after('revoked_by_user_id')->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->after('certified_by_user_id');
            $table->timestamp('revoked_at')->nullable()->after('expires_at');
            $table->timestamp('certified_at')->nullable()->after('revoked_at');
            $table->text('reason')->nullable()->after('certified_at');
            $table->text('certification_note')->nullable()->after('reason');

            $table->index(['project_id', 'revoked_at', 'expires_at'], 'project_members_active_access_index');
            $table->index(['project_id', 'certified_at'], 'project_members_certification_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_members', function (Blueprint $table) {
            $table->dropIndex('project_members_active_access_index');
            $table->dropIndex('project_members_certification_index');
            $table->dropConstrainedForeignId('granted_by_user_id');
            $table->dropConstrainedForeignId('revoked_by_user_id');
            $table->dropConstrainedForeignId('certified_by_user_id');
            $table->dropColumn([
                'expires_at',
                'revoked_at',
                'certified_at',
                'reason',
                'certification_note',
            ]);
        });
    }
};
