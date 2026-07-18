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
        Schema::create('audit_chain_heads', function (Blueprint $table): void {
            $table->foreignId('team_id')->primary()->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('next_sequence')->default(1);
            $table->string('last_signature', 64)->nullable();
            $table->timestamps();
        });

        Schema::table('audit_events', function (Blueprint $table): void {
            $table->unsignedBigInteger('sequence')->nullable()->after('team_id');
            $table->string('previous_signature', 64)->nullable()->after('sequence');
            $table->string('signature', 64)->nullable()->after('previous_signature');
            $table->string('signature_key_id')->nullable()->after('signature');
            $table->timestamp('legal_hold_until')->nullable()->after('ip_address');
            $table->timestamp('archived_at')->nullable()->after('legal_hold_until');
            $table->string('archive_receipt')->nullable()->after('archived_at');

            $table->unique(['team_id', 'sequence']);
            $table->index(['legal_hold_until', 'created_at']);
        });

        Schema::create('audit_archive_outbox', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignId('team_id')->constrained()->restrictOnDelete();
            $table->uuid('audit_event_id')->unique();
            $table->json('payload');
            $table->string('signature', 64);
            $table->string('signature_key_id');
            $table->string('status')->default('pending');
            $table->unsignedInteger('attempts')->default(0);
            $table->timestamp('available_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['status', 'available_at']);
            $table->index(['team_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('audit_archive_outbox');

        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dropUnique(['team_id', 'sequence']);
            $table->dropIndex(['legal_hold_until', 'created_at']);
            $table->dropColumn([
                'sequence',
                'previous_signature',
                'signature',
                'signature_key_id',
                'legal_hold_until',
                'archived_at',
                'archive_receipt',
            ]);
        });

        Schema::dropIfExists('audit_chain_heads');
    }
};
