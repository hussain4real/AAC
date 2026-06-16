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
        Schema::create('trace_events', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('agent_run_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('message')->nullable();
            $table->json('data')->nullable();
            $table->unsignedInteger('sequence')->default(0);
            $table->timestamp('occurred_at')->nullable();
            $table->timestamps();

            $table->index(['agent_run_id', 'sequence']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trace_events');
    }
};
