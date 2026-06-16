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
        Schema::create('tool_calls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_run_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('tool_contract_id')->nullable()->constrained()->nullOnDelete();
            $table->string('tool_name');
            $table->string('status');
            $table->json('arguments')->nullable();
            $table->json('result')->nullable();
            $table->string('execution_mode')->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamp('requested_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['agent_run_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tool_calls');
    }
};
