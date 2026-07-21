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
        Schema::table('users', function (Blueprint $table): void {
            $table->index(['name', 'id'], 'users_name_id_index');
        });

        Schema::table('team_members', function (Blueprint $table): void {
            $table->index(['team_id', 'id'], 'team_members_team_id_id_index');
        });

        Schema::table('platform_access_grants', function (Blueprint $table): void {
            $table->index(['revoked_at', 'user_id'], 'platform_grants_active_user_index');
        });

        Schema::table('audit_events', function (Blueprint $table): void {
            $table->index(['action', 'created_at', 'id'], 'audit_events_action_created_id_index');
        });

        Schema::table('llm_providers', function (Blueprint $table): void {
            $table->char('pricing_currency', 3)->default('USD')->after('output_cost');
            $table->string('pricing_unit')->default('per_million_tokens')->after('pricing_currency');
            $table->string('pricing_source')->default('MAACC governed catalog')->after('pricing_unit');
            $table->string('pricing_version')->default('2026-07-19')->after('pricing_source');
            $table->timestamp('pricing_effective_at')->nullable()->after('pricing_version');
        });

        Schema::table('agent_runs', function (Blueprint $table): void {
            $table->string('caller_subject', 128)->nullable()->after('caller_context');
            $table->string('caller_department', 128)->nullable()->after('caller_subject');
            $table->char('cost_currency', 3)->default('USD')->after('cost');
            $table->string('pricing_source')->nullable()->after('cost_currency');
            $table->string('pricing_version')->nullable()->after('pricing_source');
            $table->timestamp('pricing_effective_at')->nullable()->after('pricing_version');
            $table->index(['application_id', 'status', 'created_at'], 'agent_runs_app_status_created_index');
            $table->index(['project_id', 'status', 'created_at'], 'agent_runs_project_status_created_index');
            $table->index(['agent_id', 'created_at'], 'agent_runs_agent_created_index');
            $table->index(['application_id', 'caller_subject', 'created_at'], 'agent_runs_app_user_created_index');
            $table->index(['application_id', 'caller_department', 'created_at'], 'agent_runs_app_dept_created_index');
        });

        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->index(['team_id', 'status', 'created_at'], 'approval_team_status_created_index');
            $table->index(['project_id', 'status', 'created_at'], 'approval_project_status_created_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('audit_events', function (Blueprint $table): void {
            $table->dropIndex('audit_events_action_created_id_index');
        });

        Schema::table('platform_access_grants', function (Blueprint $table): void {
            $table->dropIndex('platform_grants_active_user_index');
        });

        Schema::table('team_members', function (Blueprint $table): void {
            $table->dropIndex('team_members_team_id_id_index');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex('users_name_id_index');
        });

        Schema::table('approval_requests', function (Blueprint $table): void {
            $table->dropIndex('approval_team_status_created_index');
            $table->dropIndex('approval_project_status_created_index');
        });

        Schema::table('agent_runs', function (Blueprint $table): void {
            $table->dropIndex('agent_runs_app_status_created_index');
            $table->dropIndex('agent_runs_project_status_created_index');
            $table->dropIndex('agent_runs_agent_created_index');
            $table->dropIndex('agent_runs_app_user_created_index');
            $table->dropIndex('agent_runs_app_dept_created_index');
            $table->dropColumn(['caller_subject', 'caller_department', 'cost_currency', 'pricing_source', 'pricing_version', 'pricing_effective_at']);
        });

        Schema::table('llm_providers', function (Blueprint $table): void {
            $table->dropColumn(['pricing_currency', 'pricing_unit', 'pricing_source', 'pricing_version', 'pricing_effective_at']);
        });
    }
};
