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
        Schema::create('tool_assignments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('tool_contract_id')->constrained()->cascadeOnDelete();
            $table->string('scope');
            $table->foreignUuid('project_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignUuid('agent_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('environment')->nullable();
            $table->timestamps();

            $table->unique(['tool_contract_id', 'project_id', 'agent_id'], 'tool_assignments_unique');
            $table->index('agent_id');
            $table->index('project_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tool_assignments');
    }
};
