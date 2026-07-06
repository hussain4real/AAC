<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('llm_providers', function (Blueprint $table): void {
            $table->timestamp('verified_at')->nullable()->after('vault_secret_id');
            $table->string('verification_status')->nullable()->after('verified_at');
            $table->text('verification_message')->nullable()->after('verification_status');
            $table->timestamp('verification_checked_at')->nullable()->after('verification_message');
        });

        // Grandfather existing approved catalog entries as verified so the new
        // publish gate does not retroactively invalidate models that are already
        // live in production. New entries start unverified and must pass a live
        // connection check before they can be published. 'approved' / 'ok' are
        // the string values of LlmStatus::Approved and LlmVerificationOutcome::Ok.
        DB::table('llm_providers')
            ->where('status', 'approved')
            ->update([
                'verification_status' => 'ok',
                'verification_message' => 'Grandfathered as verified when the verification gate was introduced.',
                'verified_at' => now(),
                'verification_checked_at' => now(),
            ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('llm_providers', function (Blueprint $table): void {
            $table->dropColumn([
                'verified_at',
                'verification_status',
                'verification_message',
                'verification_checked_at',
            ]);
        });
    }
};
