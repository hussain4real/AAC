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
        Schema::create('llm_providers', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('code');
            $table->string('provider');
            $table->string('context_window');
            $table->decimal('input_cost', 8, 4)->default(0);
            $table->decimal('output_cost', 8, 4)->default(0);
            $table->string('sensitivity');
            $table->json('environments');
            $table->string('status');
            $table->unsignedTinyInteger('usage_pct')->default(0);
            $table->unsignedInteger('runs_count')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_providers');
    }
};
