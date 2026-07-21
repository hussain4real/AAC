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
        Schema::table('webhook_endpoints', function (Blueprint $table): void {
            $table->text('previous_secret')->nullable()->after('secret');
            $table->timestamp('previous_secret_expires_at')->nullable()->after('previous_secret');
            $table->unsignedInteger('secret_version')->default(1)->after('previous_secret_expires_at');
            $table->unsignedBigInteger('next_delivery_sequence')->default(1)->after('secret_version');
        });

        Schema::table('webhook_deliveries', function (Blueprint $table): void {
            $table->unsignedBigInteger('event_sequence')->nullable()->after('event');
            $table->string('deduplication_key')->nullable()->after('event_sequence');
            $table->unsignedInteger('secret_version')->default(1)->after('deduplication_key');
            $table->uuid('processing_token')->nullable()->after('attempts');
            $table->timestamp('processing_claimed_at')->nullable()->after('processing_token');
            $table->unsignedInteger('replay_count')->default(0)->after('processing_claimed_at');
            $table->timestamp('retained_until')->nullable()->after('replay_count');

            $table->unique(['webhook_endpoint_id', 'event_sequence']);
            $table->unique(['webhook_endpoint_id', 'deduplication_key']);
            $table->index(['status', 'processing_claimed_at']);
            $table->index(['retained_until', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('webhook_deliveries', function (Blueprint $table): void {
            $table->dropUnique(['webhook_endpoint_id', 'event_sequence']);
            $table->dropUnique(['webhook_endpoint_id', 'deduplication_key']);
            $table->dropIndex(['status', 'processing_claimed_at']);
            $table->dropIndex(['retained_until', 'status']);
            $table->dropColumn([
                'event_sequence',
                'deduplication_key',
                'secret_version',
                'processing_token',
                'processing_claimed_at',
                'replay_count',
                'retained_until',
            ]);
        });

        Schema::table('webhook_endpoints', function (Blueprint $table): void {
            $table->dropColumn(['previous_secret', 'previous_secret_expires_at', 'secret_version', 'next_delivery_sequence']);
        });
    }
};
