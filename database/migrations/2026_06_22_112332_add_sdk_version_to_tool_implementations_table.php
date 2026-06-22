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
        Schema::table('tool_implementations', function (Blueprint $table) {
            // The SDK client package version the application reported with (Phase
            // 6C), so the compatibility dashboard can flag clients below the
            // supported minimum.
            $table->string('sdk_version', 32)->nullable()->after('language');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tool_implementations', function (Blueprint $table) {
            $table->dropColumn('sdk_version');
        });
    }
};
