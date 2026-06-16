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
        Schema::create('projects', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->string('environment');
            $table->text('description')->nullable();
            $table->string('business_owner')->nullable();
            $table->string('technical_owner')->nullable();
            $table->string('status');
            // Denormalized display rollups shown on the console list cards.
            $table->unsignedInteger('agents_count')->default(0);
            $table->unsignedInteger('tools_count')->default(0);
            $table->unsignedInteger('runs_7d')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['application_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
