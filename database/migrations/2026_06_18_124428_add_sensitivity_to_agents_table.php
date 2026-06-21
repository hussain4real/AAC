<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a data sensitivity classification to agents (tools and models are
     * already classified) so runs can inherit and enforce a sensitivity level.
     */
    public function up(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->string('sensitivity')->default('internal')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table) {
            $table->dropColumn('sensitivity');
        });
    }
};
