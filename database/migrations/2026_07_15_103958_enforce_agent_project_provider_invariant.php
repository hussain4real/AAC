<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\JoinClause;
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
        $crossTenantAgentCount = DB::table('agents')
            ->join('projects', 'projects.id', '=', 'agents.project_id')
            ->join('applications', 'applications.id', '=', 'projects.application_id')
            ->join('llm_providers', 'llm_providers.id', '=', 'agents.llm_provider_id')
            ->whereColumn('llm_providers.team_id', '!=', 'applications.team_id')
            ->count();

        if ($crossTenantAgentCount > 0) {
            throw new RuntimeException(
                "Cannot enforce the agent project/provider invariant: {$crossTenantAgentCount} legacy agent(s) reference a provider outside their project's tenant. Reassign those agents to same-tenant providers before retrying the migration.",
            );
        }

        $now = now();

        DB::table('agents')
            ->join('projects', 'projects.id', '=', 'agents.project_id')
            ->join('applications', 'applications.id', '=', 'projects.application_id')
            ->join('llm_providers', 'llm_providers.id', '=', 'agents.llm_provider_id')
            ->whereColumn('llm_providers.team_id', 'applications.team_id')
            ->select([
                'agents.id as agent_id',
                'agents.project_id',
                'agents.llm_provider_id',
            ])
            ->orderBy('agents.id')
            ->chunkById(500, function ($agents) use ($now): void {
                $links = $agents->map(fn (object $agent): array => [
                    'project_id' => $agent->project_id,
                    'llm_provider_id' => $agent->llm_provider_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->unique(fn (array $link): string => $link['project_id'].'|'.$link['llm_provider_id'])->values()->all();

                DB::table('project_llm_provider')->insertOrIgnore($links);
            }, 'agents.id', 'agent_id');

        $invalidAgentCount = DB::table('agents')
            ->leftJoin('project_llm_provider', function (JoinClause $join): void {
                $join->on('project_llm_provider.project_id', '=', 'agents.project_id')
                    ->on('project_llm_provider.llm_provider_id', '=', 'agents.llm_provider_id');
            })
            ->whereNull('project_llm_provider.id')
            ->count();

        if ($invalidAgentCount > 0) {
            throw new RuntimeException(
                "Cannot enforce the agent project/provider invariant: {$invalidAgentCount} legacy agent(s) could not be linked to their project's provider. Repair those relationships before retrying the migration.",
            );
        }

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
