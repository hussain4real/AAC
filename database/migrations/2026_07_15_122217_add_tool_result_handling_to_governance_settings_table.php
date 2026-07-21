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
        Schema::table('governance_settings', function (Blueprint $table) {
            $table->string('tool_result_handling', 16)->default('mask')->after('mask_sensitive_outputs');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('governance_settings', function (Blueprint $table) {
            $table->dropColumn('tool_result_handling');
        });
    }
};
