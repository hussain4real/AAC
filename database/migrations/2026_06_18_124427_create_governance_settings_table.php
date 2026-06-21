<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * One governance settings row per team: configurable payload retention
     * windows, masking/redaction toggles for sensitive inputs/outputs, audit
     * retention, and a default per-environment run quota. Per-environment
     * overrides are stored as JSON so retention/log settings can be separated
     * by environment (BRS environment separation controls).
     */
    public function up(): void
    {
        Schema::create('governance_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('retain_prompts_days')->default(90);
            $table->unsignedInteger('retain_responses_days')->default(90);
            $table->unsignedInteger('retain_tool_arguments_days')->default(30);
            $table->unsignedInteger('retain_tool_results_days')->default(30);
            $table->unsignedInteger('audit_retention_days')->default(365);
            $table->boolean('mask_sensitive_inputs')->default(true);
            $table->boolean('mask_sensitive_outputs')->default(true);
            $table->boolean('block_restricted_logging')->default(true);
            $table->unsignedInteger('default_daily_run_quota')->nullable();
            $table->json('environment_overrides')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('governance_settings');
    }
};
