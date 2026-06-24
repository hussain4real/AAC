<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The outcome of one evaluation case: the agent run it produced, whether it
 * passed, the per-check results (correctness/tool/citation/safety/cost/latency),
 * the citations the run surfaced, and its cost/latency. The case name and kind
 * are snapshotted so the result remains readable if the source case is later
 * edited or removed.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('evaluation_results', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('evaluation_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('evaluation_case_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('agent_run_id')->nullable()->constrained()->nullOnDelete();
            $table->string('case_name');
            $table->string('kind');
            $table->boolean('passed')->default(false);
            $table->json('checks');
            $table->json('citations')->nullable();
            $table->decimal('cost', 12, 6)->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->text('output')->nullable();
            $table->string('failure_reason')->nullable();
            $table->timestamps();

            $table->index('evaluation_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evaluation_results');
    }
};
