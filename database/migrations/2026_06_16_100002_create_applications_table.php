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
        Schema::create('applications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->string('slug')->unique();
            $table->string('code');
            $table->string('name');
            $table->string('department');
            $table->string('owner_name');
            $table->string('owner_email');
            $table->string('environment');
            $table->string('status');
            $table->string('stack')->nullable();
            $table->text('description')->nullable();
            $table->string('region')->nullable();
            $table->timestamp('last_connected_at')->nullable();
            // Denormalized display rollups shown on the console list cards.
            $table->unsignedInteger('projects_count')->default(0);
            $table->unsignedInteger('agents_count')->default(0);
            $table->unsignedInteger('tools_required')->default(0);
            $table->unsignedInteger('tools_implemented')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('applications');
    }
};
