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
            $table->string('idempotency_key', 128)->nullable()->after('correlation_id');
            $table->char('request_hash', 64)->nullable()->after('idempotency_key');
            $table->unsignedInteger('reserved_tokens')->default(0)->after('tokens_out');
            $table->unsignedInteger('next_trace_sequence')->default(0)->after('reserved_tokens');
            $table->unsignedInteger('next_tool_sequence')->default(0)->after('next_trace_sequence');
            $table->uuid('processing_token')->nullable()->after('next_tool_sequence');
            $table->timestamp('processing_claimed_at')->nullable()->after('processing_token');
            $table->boolean('terminal_event_emitted')->default(false)->after('processing_claimed_at');

            $table->unique(['application_id', 'idempotency_key']);
            $table->index(['status', 'processing_claimed_at']);
        });

        Schema::table('tool_calls', function (Blueprint $table) {
            $table->unique(['agent_run_id', 'sequence']);
        });

        Schema::table('trace_events', function (Blueprint $table) {
            $table->unique(['agent_run_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trace_events', function (Blueprint $table) {
            $table->dropUnique(['agent_run_id', 'sequence']);
        });

        Schema::table('tool_calls', function (Blueprint $table) {
            $table->dropUnique(['agent_run_id', 'sequence']);
        });

        Schema::table('agent_runs', function (Blueprint $table) {
            $table->dropUnique(['application_id', 'idempotency_key']);
            $table->dropIndex(['status', 'processing_claimed_at']);
            $table->dropColumn([
                'idempotency_key',
                'request_hash',
                'reserved_tokens',
                'next_trace_sequence',
                'next_tool_sequence',
                'processing_token',
                'processing_claimed_at',
                'terminal_event_emitted',
            ]);
        });
    }
};
