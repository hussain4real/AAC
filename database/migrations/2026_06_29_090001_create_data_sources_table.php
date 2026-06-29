<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A governed read-only data source MAAC may query on behalf of a `db` tool. The
 * source references an ops-provisioned, read-only Laravel connection by name
 * (never a plaintext connection string) and resolves any credential MAAC must
 * inject from the secrets vault (`vault_secret_id`) at query time. It carries the
 * approved query surface (`allowed_relations`), result/timeout caps, a freshness
 * marker, an environment availability list, and a sensitivity classification. A
 * sensitive source (or one explicitly flagged) starts as a draft and is gated
 * behind a data-source access approval before the runtime may query it.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('data_sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('application_id')->nullable()->constrained()->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('connection_type');
            // The name of an approved, ops-provisioned read-only Laravel
            // connection (a replica/reporting schema). MAAC stores only the
            // reference — never a connection string, host, or credential.
            $table->string('connection');
            $table->string('driver')->nullable();
            $table->foreignUuid('vault_secret_id')->nullable()->constrained('vault_secrets')->nullOnDelete();
            $table->string('status')->default('draft');
            $table->string('sensitivity');
            $table->boolean('requires_approval')->default(false);
            $table->json('environments');
            // The allowlisted views/tables a `db` tool query may reference.
            $table->json('allowed_relations');
            $table->unsignedInteger('max_rows')->default(100);
            $table->unsignedInteger('statement_timeout_ms')->default(5000);
            $table->unsignedInteger('max_result_kb')->default(256);
            $table->timestamp('data_refreshed_at')->nullable();
            $table->unsignedInteger('staleness_threshold_minutes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['team_id', 'status']);
            $table->index('application_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('data_sources');
    }
};
