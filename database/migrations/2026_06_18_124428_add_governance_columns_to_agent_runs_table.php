<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds governance/observability columns to runs: the inherited data
     * sensitivity, a categorized failure reason code (distinct from the
     * free-text error), and whether stored payloads were masked/redacted.
     */
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->string('sensitivity')->default('internal')->after('environment');
            $table->string('failure_reason')->nullable()->after('error');
            $table->boolean('masked')->default(false)->after('failure_reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_runs', function (Blueprint $table) {
            $table->dropColumn(['sensitivity', 'failure_reason', 'masked']);
        });
    }
};
