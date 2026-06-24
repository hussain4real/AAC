<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Links an agent run to the evaluation that produced it. A run carrying an
 * `evaluation_id` is an internal, first-party evaluation run: the runtime allows
 * it against an agent that is not yet published (so a candidate can be evaluated
 * before promotion), while external SDK invocations remain published-only. The
 * link also makes evaluation activity traceable from the runs/audit surfaces.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('agent_runs', function (Blueprint $table): void {
            $table->foreignUuid('evaluation_id')->nullable()->after('agent_id')->constrained('evaluations')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agent_runs', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('evaluation_id');
        });
    }
};
