<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds execution configuration for the two server-side tool modes introduced in
 * this phase. `http_config` (encrypted — it may hold auth material) carries a
 * remote HTTP tool's method, endpoint, auth, and retry policy. `mcp_connector_id`
 * + `mcp_tool_name` map an MCP-backed tool contract to a registered connector and
 * the remote tool to invoke. `redaction` lists result field paths to mask in the
 * stored trace/audit copy (the live LLM path still sees raw values).
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tool_contracts', function (Blueprint $table): void {
            $table->text('http_config')->nullable()->after('output_schema');
            $table->foreignUuid('mcp_connector_id')->nullable()->after('http_config')->constrained('mcp_connectors')->nullOnDelete();
            $table->string('mcp_tool_name')->nullable()->after('mcp_connector_id');
            $table->json('redaction')->nullable()->after('mcp_tool_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tool_contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('mcp_connector_id');
            $table->dropColumn(['http_config', 'mcp_tool_name', 'redaction']);
        });
    }
};
