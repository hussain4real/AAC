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
        Schema::create('agents', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('project_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('llm_provider_id')->constrained()->restrictOnDelete();
            // Points at the published agent_versions row. No FK constraint to avoid a
            // circular dependency with agent_versions.agent_id; resolved via Eloquent.
            $table->uuid('current_version_id')->nullable()->index();
            $table->string('slug')->unique();
            $table->string('agent_slug')->unique();
            $table->string('name');
            $table->string('version')->default('v1');
            $table->string('status');
            $table->text('system_prompt');
            $table->decimal('temperature', 3, 2)->default(0.2);
            $table->unsignedInteger('max_tokens')->default(1500);
            $table->text('description')->nullable();
            $table->decimal('success_rate', 5, 2)->default(0);
            $table->unsignedInteger('runs_7d')->default(0);
            $table->timestamp('last_run_at')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['project_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
