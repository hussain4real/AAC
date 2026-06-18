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
            // The SDK runtime language the application reported the handler in.
            $table->string('language')->nullable()->after('implemented_version');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tool_implementations', function (Blueprint $table) {
            $table->dropColumn('language');
        });
    }
};
