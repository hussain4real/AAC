<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Registered external MCP (Model Context Protocol) servers that MAAC connects to
 * as a client. A connector exposes one or more remote tools; an MCP-backed tool
 * contract (execution_mode = connector) maps to a connector + a remote tool
 * name. Auth material is encrypted at rest and never returned to the console or
 * SDK; discovered `capabilities` are cached for the console and permission
 * mapping.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mcp_connectors', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('transport')->default('http');
            $table->string('server_url');
            $table->string('auth_type')->default('none');
            $table->text('auth_credential')->nullable();
            $table->string('auth_header')->nullable();
            $table->string('sensitivity');
            $table->boolean('requires_approval')->default(false);
            $table->string('status')->default('active');
            $table->json('environments');
            $table->json('capabilities')->nullable();
            $table->unsignedInteger('timeout_seconds')->default(20);
            $table->timestamp('last_discovered_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index('application_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mcp_connectors');
    }
};
