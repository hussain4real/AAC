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
        Schema::table('agents', function (Blueprint $table): void {
            $table->foreign(
                ['project_id', 'llm_provider_id'],
                'agents_project_provider_foreign',
            )
                ->references(['project_id', 'llm_provider_id'])
                ->on('project_llm_provider')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('agents', function (Blueprint $table): void {
            $table->dropForeign('agents_project_provider_foreign');
        });
    }
};
