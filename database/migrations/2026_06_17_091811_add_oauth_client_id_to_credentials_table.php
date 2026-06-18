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
        Schema::table('credentials', function (Blueprint $table) {
            // The Passport client_credentials client that backs this credential
            // for SDK/API token issuance. Null for legacy/test credentials that
            // have no backing OAuth client.
            $table->uuid('oauth_client_id')->nullable()->after('client_id');
            $table->index('oauth_client_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('credentials', function (Blueprint $table) {
            $table->dropIndex(['oauth_client_id']);
            $table->dropColumn('oauth_client_id');
        });
    }
};
