<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bridges databases created before the MAAC → MAACC rebrand. Fresh installs
 * already have the renamed schema/data (the create migrations and seeders were
 * rebranded), so every step here is guarded to be a no-op in that case; only
 * pre-rebrand databases (existing dev/production) are updated.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('project_members', 'maac_role')
            && ! Schema::hasColumn('project_members', 'maacc_role')) {
            Schema::table('project_members', function (Blueprint $table): void {
                $table->renameColumn('maac_role', 'maacc_role');
            });
        }

        DB::table('data_sources')
            ->where('connection', 'maac_reporting')
            ->update(['connection' => 'maacc_reporting']);
    }

    public function down(): void
    {
        if (Schema::hasColumn('project_members', 'maacc_role')
            && ! Schema::hasColumn('project_members', 'maac_role')) {
            Schema::table('project_members', function (Blueprint $table): void {
                $table->renameColumn('maacc_role', 'maac_role');
            });
        }

        DB::table('data_sources')
            ->where('connection', 'maacc_reporting')
            ->update(['connection' => 'maac_reporting']);
    }
};
