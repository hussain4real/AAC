<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sso_connections', function (Blueprint $table) {
            $table->string('issuer')->nullable()->after('provider');
            $table->string('jwks_url')->nullable()->after('userinfo_url');
            $table->json('allowed_domains')->nullable()->after('groups_claim');
            $table->timestamp('tested_at')->nullable()->after('status');
            $table->timestamp('approved_at')->nullable()->after('tested_at');
            $table->foreignId('approved_by')->nullable()->after('approved_at')->constrained('users')->nullOnDelete();
        });

        Schema::table('sso_identities', function (Blueprint $table): void {
            $table->json('managed_project_ids')->nullable()->after('raw_claims');
            $table->unique(['sso_connection_id', 'user_id']);
        });

        DB::table('sso_connections')
            ->where('status', 'active')
            ->update(['status' => 'disabled', 'updated_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sso_connections', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['issuer', 'jwks_url', 'allowed_domains', 'tested_at', 'approved_at']);
        });

        Schema::table('sso_identities', function (Blueprint $table): void {
            $table->dropUnique(['sso_connection_id', 'user_id']);
            $table->dropColumn('managed_project_ids');
        });
    }
};
