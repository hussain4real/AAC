<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds the columns the Phase 4 runtime needs to drive and resume a run:
     * the executing environment, the final response, the serialized
     * conversation state used to resume a paused run, and the expiry deadline.
     */
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->string('environment')->nullable()->after('caller');
            $table->text('output')->nullable()->after('input');
            $table->json('state')->nullable()->after('output');
            $table->timestamp('expires_at')->nullable()->after('completed_at');

            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->dropIndex(['agent_runs_status_expires_at_index']);
            $table->dropColumn(['environment', 'output', 'state', 'expires_at']);
        });
    }
};
