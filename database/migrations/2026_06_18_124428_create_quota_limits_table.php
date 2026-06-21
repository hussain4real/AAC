<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Rate limits / quotas matrix: a per-day run and/or token cap scoped to a
     * dimension (platform, application, project, agent, or model) and optionally
     * narrowed to a single environment (BRS rate limits & quotas).
     */
    public function up(): void
    {
        Schema::create('quota_limits', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('scope');
            $table->string('subject_id')->nullable();
            $table->string('environment')->nullable();
            $table->unsignedInteger('max_runs_per_day')->nullable();
            $table->unsignedBigInteger('max_tokens_per_day')->nullable();
            $table->boolean('enabled')->default(true);
            $table->timestamps();

            $table->index(['team_id', 'scope', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('quota_limits');
    }
};
