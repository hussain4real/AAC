<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A run of a golden dataset against an agent. It snapshots the agent's version,
 * model, and prompt fingerprint at run time (so comparison reports can attribute
 * a behavior change), records rolled-up outcome metrics (pass rate, cost,
 * latency, correctness/safety/citation rates), and — when flagged `is_required`
 * — acts as a promotion gate: the agent cannot be published while its latest
 * required evaluation has not passed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('evaluations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('evaluation_dataset_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_version_id')->nullable()->constrained('agent_versions')->nullOnDelete();
            $table->string('environment');
            $table->string('label');
            $table->string('status')->default('pending');
            $table->boolean('is_required')->default(false);
            $table->string('agent_version');
            $table->string('model_code')->nullable();
            $table->string('prompt_fingerprint')->nullable();
            $table->unsignedInteger('cases_total')->default(0);
            $table->unsignedInteger('cases_passed')->default(0);
            $table->decimal('pass_rate', 5, 2)->default(0);
            $table->decimal('total_cost', 12, 6)->default(0);
            $table->unsignedInteger('avg_latency_ms')->default(0);
            $table->decimal('correctness_rate', 5, 2)->default(0);
            $table->decimal('safety_rate', 5, 2)->default(0);
            $table->decimal('citation_rate', 5, 2)->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('team_id');
            $table->index(['agent_id', 'is_required']);
            $table->index('evaluation_dataset_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluations');
    }
};
