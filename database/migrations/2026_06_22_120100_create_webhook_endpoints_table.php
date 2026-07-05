<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Application-registered webhook endpoints. MAACC posts run lifecycle events
 * (status changes, tool requests, completion, failure, expiry) to the `url`,
 * signed with the endpoint `secret`. The secret is encrypted at rest and shown
 * to the registrant only once; `last_four` is retained for display.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webhook_endpoints', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('application_id')->constrained()->cascadeOnDelete();
            $table->string('environment');
            $table->string('url');
            $table->text('secret');
            $table->string('last_four', 8)->nullable();
            $table->json('events');
            $table->string('description')->nullable();
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_delivered_at')->nullable();
            $table->timestamp('last_failed_at')->nullable();
            $table->timestamps();

            $table->index(['application_id', 'environment', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_endpoints');
    }
};
