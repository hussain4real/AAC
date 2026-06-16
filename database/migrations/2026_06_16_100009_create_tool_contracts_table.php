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
        Schema::create('tool_contracts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('scope');
            $table->string('execution_mode');
            $table->string('sensitivity');
            $table->boolean('requires_approval')->default(false);
            $table->string('status')->default('Active');
            $table->string('implementation_status');
            $table->unsignedInteger('timeout_seconds')->default(15);
            $table->unsignedInteger('max_payload_kb')->default(256);
            $table->json('input_schema');
            $table->json('output_schema');
            $table->string('version')->default('1.0.0');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'scope']);
            $table->index('application_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tool_contracts');
    }
};
