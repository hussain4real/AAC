<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Governance approval queue: gates sensitive tool contracts, agent
     * publication, model environment promotion, and production credential
     * changes behind an approver decision (BRS governance workflows).
     */
    public function up(): void
    {
        Schema::create('approval_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('application_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignUuid('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->string('subject_type')->nullable();
            $table->string('subject_id')->nullable();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('sensitivity')->nullable();
            $table->string('environment')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('requested_label')->nullable();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('decided_label')->nullable();
            $table->text('decision_note')->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'status']);
            $table->index(['type', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_requests');
    }
};
