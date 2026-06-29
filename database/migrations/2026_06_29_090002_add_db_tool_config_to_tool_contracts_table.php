<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds execution configuration for the governed read-only database (`db`) tool
 * mode. `data_source_id` maps a `db` tool contract to an approved read-only data
 * source; `db_config` carries the parameterized SELECT template, its input
 * bindings, the projected (minimized) output columns, the per-query row limit,
 * and the freshness expectation. The config holds no secrets (the credential
 * lives in the vault via the data source), so it is stored as plain JSON and is
 * reviewable for the query-surface approval.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('tool_contracts', function (Blueprint $table): void {
            $table->foreignUuid('data_source_id')->nullable()->after('knowledge_config')->constrained('data_sources')->nullOnDelete();
            $table->json('db_config')->nullable()->after('data_source_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tool_contracts', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('data_source_id');
            $table->dropColumn('db_config');
        });
    }
};
