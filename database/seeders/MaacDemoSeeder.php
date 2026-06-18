<?php

namespace Database\Seeders;

use App\Enums\AgentStatus;
use App\Enums\CredentialStatus;
use App\Enums\ImplStatus;
use App\Enums\MaacRole;
use App\Enums\ToolScope;
use App\Enums\TraceEventType;
use App\Models\Agent;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\AuditEvent;
use App\Models\Credential;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\Team;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use App\Models\User;
use App\Support\Sdk\SdkClientManager;
use Carbon\CarbonInterface;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds the MAAC demo team with the Phase 1 console fixture
 * (resources/js/maac/data.ts) as governed database records. Every entity keeps
 * its fixture identifier as its slug so the console screens, URLs, and the
 * cross-references between records remain stable. Idempotent: keyed on slug.
 */
class MaacDemoSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the MAAC platform data for the demo team.
     */
    public function run(): void
    {
        $user = User::firstWhere('email', 'demo@milaha.com')
            ?? User::factory()->create(['name' => 'Layla Hassan', 'email' => 'demo@milaha.com']);

        /** @var Team $team */
        $team = $user->currentTeam ?? $user->personalTeam();

        $llms = $this->seedLlmProviders($team);
        $apps = $this->seedApplications($team, $user);
        $projects = $this->seedProjects($apps, $llms);
        $tools = $this->seedToolContracts($team, $apps);
        $agents = $this->seedAgents($projects, $apps, $llms, $tools, $user);
        $this->seedRuns($agents, $apps, $projects, $llms, $tools);
        $this->seedProjectMembers($projects, $user);
        $this->seedAuditEvents($team, $apps, $agents, $user);
    }

    /**
     * @return array<string, LlmProvider>
     */
    private function seedLlmProviders(Team $team): array
    {
        $rows = [
            ['gpt-4o', 'GPT-4o', 'azure/gpt-4o', 'Azure OpenAI', '128K', 2.5, 10.0, 'restricted', ['production', 'staging', 'development'], 'approved', 34, 6840, 'General reasoning, vision, broad tool use.'],
            ['gpt-4o-mini', 'GPT-4o mini', 'azure/gpt-4o-mini', 'Azure OpenAI', '128K', 0.15, 0.6, 'internal', ['production', 'staging', 'development'], 'approved', 27, 5410, 'Low-cost classification & summarization.'],
            ['claude-37-sonnet', 'Claude 3.7 Sonnet', 'bedrock/claude-3-7-sonnet', 'AWS Bedrock', '200K', 3.0, 15.0, 'restricted', ['production', 'staging', 'development'], 'approved', 21, 4230, 'Long-context analysis, careful tool reasoning.'],
            ['claude-haiku', 'Claude 3.5 Haiku', 'bedrock/claude-3-5-haiku', 'AWS Bedrock', '200K', 0.8, 4.0, 'internal', ['production', 'staging', 'development'], 'approved', 9, 1870, 'Fast, cheap extraction & routing.'],
            ['gemini-15-pro', 'Gemini 1.5 Pro', 'vertex/gemini-1.5-pro', 'Google Vertex AI', '1M', 1.25, 5.0, 'restricted', ['staging', 'development'], 'approved', 6, 1180, 'Very long document ingestion.'],
            ['llama3-70b', 'Llama 3.1 70B', 'onprem/llama-3.1-70b', 'Milaha On-Prem GPU', '128K', 0.0, 0.0, 'confidential', ['production', 'staging', 'development'], 'approved', 3, 640, 'On-prem, no data egress. Highly sensitive workloads.'],
            ['gpt-35-turbo', 'GPT-3.5 Turbo', 'azure/gpt-3.5-turbo', 'Azure OpenAI', '16K', 0.5, 1.5, 'internal', ['development'], 'deprecated', 0, 90, 'Superseded by GPT-4o mini.'],
        ];

        $out = [];
        foreach ($rows as [$slug, $name, $code, $provider, $ctx, $in, $cost, $sens, $envs, $status, $usage, $runs, $note]) {
            $out[$slug] = LlmProvider::updateOrCreate(['slug' => $slug], [
                'team_id' => $team->id,
                'name' => $name,
                'code' => $code,
                'provider' => $provider,
                'context_window' => $ctx,
                'input_cost' => $in,
                'output_cost' => $cost,
                'sensitivity' => $sens,
                'environments' => $envs,
                'status' => $status,
                'usage_pct' => $usage,
                'runs_count' => $runs,
                'note' => $note,
            ]);
        }

        return $out;
    }

    /**
     * @return array<string, Application>
     */
    private function seedApplications(Team $team, User $user): array
    {
        $rows = [
            ['MOP', 'marine-ops-portal', 'Marine Operations Portal', 'Maritime & Logistics', 'Khalid Al-Mansoori', 'k.almansoori@milaha.com', 'production', 'active', 3, 4, 6, 5, '2 min ago', 'Laravel · PHP 8.3', 'Vessel scheduling, berth allocation, and live operations dashboards for the fleet.', 'active', 'Qatar — Doha DC', '12 Jan 2026'],
            ['FWS', 'finance-workflow', 'Finance Workflow System', 'Finance', 'Aisha Rahman', 'a.rahman@milaha.com', 'production', 'active', 2, 3, 4, 4, '14 min ago', 'Spring Boot · Java 21', 'Invoice approval, payment runs, and financial exception handling.', 'active', 'Qatar — Doha DC', '3 Feb 2026'],
            ['PMA', 'procure-mgmt', 'Procurement Management App', 'Procurement', 'Yousef Haddad', 'y.haddad@milaha.com', 'staging', 'active', 2, 2, 5, 2, '1 hr ago', 'Node.js · NestJS', 'Purchase requisitions, supplier records, and procurement analytics.', 'active', 'Qatar — Doha DC', '19 Feb 2026'],
            ['CSP', 'customer-service', 'Customer Service Portal', 'Customer Experience', 'Lina Farouk', 'l.farouk@milaha.com', 'production', 'active', 1, 2, 3, 3, '6 min ago', 'Django · Python 3.12', 'Customer interactions, case management, and CSAT tracking.', 'active', 'Qatar — Doha DC', '28 Feb 2026'],
            ['VMS', 'vessel-maint', 'Vessel Maintenance System', 'Marine & Technical Services', 'Omar Sheikh', 'o.sheikh@milaha.com', 'development', 'suspended', 1, 1, 4, 1, '3 days ago', '.NET 8 · C#', 'Maintenance schedules, work orders, and asset risk monitoring.', 'revoked', 'Qatar — Doha DC', '5 Mar 2026'],
        ];

        $out = [];
        foreach ($rows as [$slug, $code, $name, $dept, $owner, $email, $env, $status, $projects, $agents, $treq, $timpl, $lastConn, $stack, $desc, $credStatus, $region, $created]) {
            $app = Application::updateOrCreate(['slug' => $slug], [
                'team_id' => $team->id,
                'code' => $code,
                'name' => $name,
                'department' => $dept,
                'owner_name' => $owner,
                'owner_email' => $email,
                'environment' => $env,
                'status' => $status,
                'stack' => $stack,
                'description' => $desc,
                'region' => $region,
                'last_connected_at' => $this->relativeToCarbon($lastConn),
                'projects_count' => $projects,
                'agents_count' => $agents,
                'tools_required' => $treq,
                'tools_implemented' => $timpl,
            ]);
            $app->created_at = Carbon::createFromFormat('d M Y', $created)->startOfDay();
            $app->save();
            $out[$slug] = $app;

            $this->seedCredential($app, $user, $credStatus);
        }

        return $out;
    }

    /**
     * Create or update the application's environment credential, generating a
     * hashed secret only on first creation (so re-seeding keeps it stable).
     */
    private function seedCredential(Application $app, User $user, string $credStatus): void
    {
        $cred = Credential::firstOrNew([
            'application_id' => $app->id,
            'environment' => $app->environment->value,
        ]);

        if (! $cred->exists) {
            // Back the demo credential with a real Passport client so it can be
            // exchanged for SDK tokens at /oauth/token.
            app(SdkClientManager::class)->provision(
                $cred,
                $app->name.' — '.$app->environment->label(),
            );
            $cred->created_by = $user->id;
        }

        $revoked = $credStatus === 'revoked';
        $cred->label = $app->environment->label().' credentials';
        $cred->status = $revoked ? CredentialStatus::Revoked : CredentialStatus::Active;
        $cred->revoked_at = $revoked ? Carbon::now()->subHour() : null;
        $cred->last_used_at = $app->last_connected_at;
        $cred->save();
    }

    /**
     * @param  array<string, Application>  $apps
     * @param  array<string, LlmProvider>  $llms
     * @return array<string, Project>
     */
    private function seedProjects(array $apps, array $llms): array
    {
        $rows = [
            ['prj_mop_ops', 'MOP', 'Fleet Operations Intelligence', 'production', 'Operational summaries and exception detection across active voyages.', 'Khalid Al-Mansoori', 'Reema Saleh', 'active', ['gpt-4o', 'claude-37-sonnet'], 2, 5, 1840],
            ['prj_mop_berth', 'MOP', 'Berth & Schedule Advisor', 'production', 'Decision support for berth allocation and schedule conflicts.', 'Khalid Al-Mansoori', 'Reema Saleh', 'active', ['gpt-4o', 'gpt-4o-mini'], 1, 3, 760],
            ['prj_mop_docs', 'MOP', 'Marine Document Review', 'staging', 'Reviews bills of lading and shipping documents for completeness.', 'Noura Adel', 'Reema Saleh', 'active', ['claude-37-sonnet'], 1, 2, 210],
            ['prj_fws_appr', 'FWS', 'Approval & Exception Desk', 'production', 'Reviews pending approvals and surfaces financial exceptions.', 'Aisha Rahman', 'Tariq Nabil', 'active', ['gpt-4o', 'claude-37-sonnet'], 2, 4, 1320],
            ['prj_fws_close', 'FWS', 'Month-End Close Assist', 'staging', 'Assists analysts during the financial close cycle.', 'Aisha Rahman', 'Tariq Nabil', 'active', ['claude-37-sonnet'], 1, 2, 140],
            ['prj_pma_insight', 'PMA', 'Procurement Insight', 'staging', 'Analyzes purchase requests and supplier performance.', 'Yousef Haddad', 'Hadi Karam', 'active', ['gpt-4o-mini', 'gpt-4o'], 2, 5, 430],
            ['prj_csp_trend', 'CSP', 'Customer Trend Analysis', 'production', 'Surfaces emerging themes in customer interactions.', 'Lina Farouk', 'Sami Diab', 'active', ['gpt-4o', 'claude-haiku'], 2, 3, 980],
            ['prj_vms_risk', 'VMS', 'Maintenance Risk Watch', 'development', 'Flags assets at risk based on maintenance history.', 'Omar Sheikh', 'Bilal Aziz', 'archived', ['llama3-70b'], 1, 4, 0],
        ];

        $out = [];
        foreach ($rows as [$slug, $appSlug, $name, $env, $desc, $biz, $tech, $status, $llmSlugs, $agents, $tools, $runs7d]) {
            $project = Project::updateOrCreate(['slug' => $slug], [
                'application_id' => $apps[$appSlug]->id,
                'name' => $name,
                'environment' => $env,
                'description' => $desc,
                'business_owner' => $biz,
                'technical_owner' => $tech,
                'status' => $status,
                'agents_count' => $agents,
                'tools_count' => $tools,
                'runs_7d' => $runs7d,
            ]);

            $project->llmProviders()->sync(
                collect($llmSlugs)->map(fn (string $s): string => $llms[$s]->id)->all()
            );
            $out[$slug] = $project;
        }

        return $out;
    }

    /**
     * @param  array<string, Application>  $apps
     * @return array<string, ToolContract>
     */
    private function seedToolContracts(Team $team, array $apps): array
    {
        $rows = [
            ['getOperationalRecords', 'project', 'client', 'confidential', true, 'implemented', 'MOP', 'Retrieves approved operational voyage records for a date range.', 15, 256, ['from_date' => 'string·date', 'to_date' => 'string·date', 'vessel_id' => 'string?', 'status' => 'string?'], ['summary' => 'object', 'records' => 'array']],
            ['getPendingApprovals', 'project', 'client', 'restricted', true, 'implemented', 'FWS', "Lists approval items pending the current user's action.", 10, 128, ['queue' => 'string', 'assignee_id' => 'string?', 'limit' => 'number?'], ['items' => 'array', 'total' => 'number']],
            ['getProcurementRequests', 'project', 'client', 'confidential', true, 'required', 'PMA', 'Fetches purchase requisitions filtered by status and department.', 15, 256, ['from_date' => 'string·date', 'to_date' => 'string·date', 'department' => 'string?', 'status' => 'string?'], ['summary' => 'object', 'requests' => 'array']],
            ['getCustomerInteractions', 'project', 'client', 'restricted', true, 'implemented', 'CSP', 'Returns anonymized customer interaction records for analysis.', 12, 256, ['from_date' => 'string·date', 'to_date' => 'string·date', 'channel' => 'string?'], ['summary' => 'object', 'interactions' => 'array']],
            ['getFinancialTransactions', 'agent', 'client', 'restricted', true, 'outdated', 'FWS', 'Reads financial transactions for exception analysis. Masks account numbers.', 15, 256, ['from_date' => 'string·date', 'to_date' => 'string·date', 'cost_center' => 'string?', 'min_amount' => 'number?'], ['summary' => 'object', 'transactions' => 'array']],
            ['getMaintenanceSchedules', 'project', 'client', 'confidential', false, 'required', 'VMS', 'Returns maintenance schedules and overdue work orders by asset.', 15, 256, ['asset_id' => 'string?', 'overdue_only' => 'boolean?', 'horizon_days' => 'number?'], ['summary' => 'object', 'schedules' => 'array']],
            ['searchPolicyDocuments', 'global', 'knowledge', 'internal', false, 'ready', null, 'Semantic search over indexed company policy & manuals.', 8, 512, ['query' => 'string', 'top_k' => 'number?'], ['matches' => 'array']],
            ['summarizeUploadedDocument', 'global', 'hosted', 'internal', false, 'ready', null, 'Summarizes an uploaded document passed inline to MAAC.', 20, 1024, ['document_ref' => 'string', 'length' => 'string?'], ['summary' => 'string', 'key_points' => 'array']],
            ['webSearch', 'global', 'hosted', 'public', false, 'ready', null, 'Approved external web search via the platform gateway.', 10, 256, ['query' => 'string', 'recency_days' => 'number?'], ['results' => 'array']],
            ['notifyWorkflowOwner', 'global', 'http', 'internal', true, 'ready', null, 'Sends a notification to a workflow owner via the internal Notify API.', 6, 32, ['recipient_id' => 'string', 'message' => 'string', 'priority' => 'string?'], ['delivered' => 'boolean', 'notification_id' => 'string']],
        ];

        $out = [];
        foreach ($rows as [$slug, $scope, $exec, $sens, $approval, $impl, $ownerSlug, $desc, $timeout, $payload, $input, $output]) {
            $tool = ToolContract::updateOrCreate(['slug' => $slug], [
                'team_id' => $team->id,
                'application_id' => $ownerSlug ? $apps[$ownerSlug]->id : null,
                'name' => $slug,
                'description' => $desc,
                'scope' => $scope,
                'execution_mode' => $exec,
                'sensitivity' => $sens,
                'requires_approval' => $approval,
                'status' => 'Active',
                'implementation_status' => $impl,
                'timeout_seconds' => $timeout,
                'max_payload_kb' => $payload,
                'input_schema' => $input,
                'output_schema' => $output,
                'version' => '1.0.0',
            ]);
            $out[$slug] = $tool;

            // Global tools get a platform-wide availability assignment.
            if ($tool->scope === ToolScope::Global) {
                $tool->assignments()->updateOrCreate(
                    ['project_id' => null, 'agent_id' => null],
                    ['scope' => ToolScope::Global],
                );
            }

            // Client-side, application-owned tools get a per-environment
            // implementation record mirroring the contract's headline status.
            if ($tool->application_id !== null) {
                $implemented = in_array($tool->implementation_status, [ImplStatus::Implemented, ImplStatus::Outdated], true);
                $tool->implementations()->updateOrCreate(
                    ['application_id' => $tool->application_id, 'environment' => $tool->application->environment->value],
                    [
                        'status' => $tool->implementation_status,
                        'handler_name' => $implemented ? $tool->slug : null,
                        'implemented_version' => $implemented ? '1.0.0' : null,
                        'last_validated_at' => $implemented ? Carbon::now()->subDays(2) : null,
                    ],
                );
            }
        }

        return $out;
    }

    /**
     * @param  array<string, Project>  $projects
     * @param  array<string, Application>  $apps
     * @param  array<string, LlmProvider>  $llms
     * @param  array<string, ToolContract>  $tools
     * @return array<string, Agent>
     */
    private function seedAgents(array $projects, array $apps, array $llms, array $tools, User $user): array
    {
        $rows = [
            ['ag_ops_summary', 'Operations Summary Agent', 'prj_mop_ops', 'gpt-4o', 'v4', 'published', 98.4, '1 min ago', 1240, 'Summarizes daily fleet operations and surfaces voyage exceptions for duty managers.', ['getOperationalRecords', 'searchPolicyDocuments', 'notifyWorkflowOwner'], 'operations-summary', 0.3, 1500, "You are the Operations Summary Agent for Milaha's Marine Operations Portal. Produce concise, factual daily operations summaries for duty managers. Always ground statements in the operational records returned by tools. Flag any voyage with a delay over 6 hours or a compliance exception. Never speculate beyond the retrieved data."],
            ['ag_approval_review', 'Approval Review Agent', 'prj_fws_appr', 'claude-37-sonnet', 'v3', 'published', 99.1, '3 min ago', 880, 'Reviews pending approvals, checks policy thresholds, and recommends an action.', ['getPendingApprovals', 'searchPolicyDocuments', 'notifyWorkflowOwner'], 'approval-review', 0.2, 1200, 'You are the Approval Review Agent. Review pending approval items against finance policy. Recommend Approve, Reject, or Escalate with a one-line justification grounded in policy and the item details. Do not approve items above the policy threshold.'],
            ['ag_procure_insight', 'Procurement Insight Agent', 'prj_pma_insight', 'gpt-4o-mini', 'v2', 'testing', 94.7, '22 min ago', 310, 'Analyzes purchase requisitions and supplier performance trends.', ['getProcurementRequests', 'webSearch'], 'procurement-insight', 0.4, 1400, 'You are the Procurement Insight Agent. Analyze procurement requests and supplier data to surface cost-saving opportunities and supplier risk. Be specific and quantify findings where possible.'],
            ['ag_customer_trend', 'Customer Trend Agent', 'prj_csp_trend', 'gpt-4o', 'v5', 'published', 97.2, '8 min ago', 640, 'Surfaces emerging themes and sentiment shifts in customer interactions.', ['getCustomerInteractions', 'webSearch'], 'customer-trend', 0.5, 1600, 'You are the Customer Trend Agent. Identify emerging themes, recurring complaints, and sentiment shifts from customer interactions. Group findings into themes with representative examples and an estimated frequency.'],
            ['ag_fin_exception', 'Financial Exception Agent', 'prj_fws_appr', 'claude-37-sonnet', 'v2', 'published', 96.8, '12 min ago', 420, 'Detects anomalous financial transactions and routes them for review.', ['getFinancialTransactions', 'getPendingApprovals', 'notifyWorkflowOwner'], 'financial-exception', 0.2, 1300, 'You are the Financial Exception Agent. Detect anomalous transactions (duplicate payments, threshold breaches, unusual vendors). Explain why each is flagged and recommend a next action. Treat all amounts as confidential.'],
            ['ag_maint_risk', 'Maintenance Risk Agent', 'prj_vms_risk', 'llama3-70b', 'v1', 'draft', 0, '—', 0, 'Flags assets at elevated risk based on maintenance history and overdue work orders.', ['getMaintenanceSchedules', 'searchPolicyDocuments'], 'maintenance-risk', 0.3, 1400, 'You are the Maintenance Risk Agent. Assess asset risk from maintenance schedules and overdue work orders. Rank assets by risk and recommend prioritized maintenance actions.'],
            ['ag_doc_review', 'Document Review Agent', 'prj_mop_docs', 'claude-37-sonnet', 'v2', 'testing', 95.5, '35 min ago', 180, 'Reviews shipping documents for completeness and policy compliance.', ['summarizeUploadedDocument', 'searchPolicyDocuments', 'getOperationalRecords'], 'document-review', 0.2, 1800, 'You are the Document Review Agent. Review shipping documents for missing fields and policy compliance. List issues found and cite the relevant policy section.'],
            ['ag_compliance', 'Compliance Assistant Agent', 'prj_fws_close', 'gpt-4o', 'v1', 'disabled', 92.0, '2 days ago', 0, 'Answers compliance questions grounded in company policy documents.', ['searchPolicyDocuments', 'summarizeUploadedDocument'], 'compliance-assistant', 0.1, 1500, 'You are the Compliance Assistant Agent. Answer compliance questions strictly from indexed policy documents. Always cite the policy reference. If the answer is not in policy, say so.'],
        ];

        $out = [];
        foreach ($rows as [$slug, $name, $projectSlug, $llmSlug, $version, $status, $success, $lastRun, $runs7d, $desc, $toolSlugs, $agentSlug, $temp, $maxTokens, $prompt]) {
            $project = $projects[$projectSlug];
            $llm = $llms[$llmSlug];
            $lastRunAt = $this->relativeToCarbon($lastRun);
            $isPublished = AgentStatus::from($status)->isPublished();

            $agent = Agent::updateOrCreate(['slug' => $slug], [
                'project_id' => $project->id,
                'llm_provider_id' => $llm->id,
                'agent_slug' => $agentSlug,
                'name' => $name,
                'version' => $version,
                'status' => $status,
                'system_prompt' => $prompt,
                'temperature' => $temp,
                'max_tokens' => $maxTokens,
                'description' => $desc,
                'success_rate' => $success,
                'runs_7d' => $runs7d,
                'last_run_at' => $lastRunAt,
                'published_at' => $isPublished ? $lastRunAt : null,
            ]);

            $agentVersion = $agent->versions()->updateOrCreate(['version' => $version], [
                'system_prompt' => $prompt,
                'llm_provider_id' => $llm->id,
                'temperature' => $temp,
                'max_tokens' => $maxTokens,
                'settings' => ['temperature' => $temp, 'max_tokens' => $maxTokens],
                'status' => $status,
                'published_at' => $isPublished ? $lastRunAt : null,
                'published_by' => $isPublished ? $user->id : null,
            ]);
            $agent->update(['current_version_id' => $agentVersion->id]);

            // Agent-level tool assignments form the agent <-> tool usage graph.
            // Created through the model so the UUID primary key is generated.
            foreach ($toolSlugs as $toolSlug) {
                ToolAssignment::updateOrCreate(
                    ['tool_contract_id' => $tools[$toolSlug]->id, 'agent_id' => $agent->id, 'project_id' => null],
                    ['scope' => 'agent'],
                );
            }

            $out[$slug] = $agent;
        }

        return $out;
    }

    /**
     * @param  array<string, Agent>  $agents
     * @param  array<string, Application>  $apps
     * @param  array<string, Project>  $projects
     * @param  array<string, LlmProvider>  $llms
     * @param  array<string, ToolContract>  $tools
     */
    private function seedRuns(array $agents, array $apps, array $projects, array $llms, array $tools): void
    {
        $rows = [
            ['run_8fa31c', 'ag_ops_summary', 'MOP', 'prj_mop_ops', 'k.almansoori', 'completed', 'gpt-4o', ['getOperationalRecords', 'notifyWorkflowOwner'], 3120, 840, 0.0162, '4.2s', '08 Jun 09:41:22', '08 Jun 09:41:26', "Summarize this morning's vessel operations and flag any delays over 6 hours.", null],
            ['run_7be902', 'ag_approval_review', 'FWS', 'prj_fws_appr', 'a.rahman', 'completed', 'claude-37-sonnet', ['getPendingApprovals', 'searchPolicyDocuments'], 2480, 610, 0.0166, '3.8s', '08 Jun 09:38:05', '08 Jun 09:38:09', 'Review my pending approvals and recommend an action for each.', null],
            ['run_a14d70', 'ag_procure_insight', 'PMA', 'prj_pma_insight', 'y.haddad', 'waiting_for_client', 'gpt-4o-mini', ['getProcurementRequests'], 1840, 0, 0.0003, '—', '08 Jun 09:44:51', '—', 'Which suppliers had the most delayed deliveries last quarter?', null],
            ['run_c92e18', 'ag_customer_trend', 'CSP', 'prj_csp_trend', 'l.farouk', 'completed', 'gpt-4o', ['getCustomerInteractions', 'webSearch'], 4210, 1180, 0.0223, '6.1s', '08 Jun 09:30:14', '08 Jun 09:30:20', 'What are the top emerging complaint themes this week?', null],
            ['run_d33a55', 'ag_fin_exception', 'FWS', 'prj_fws_appr', 't.nabil', 'failed', 'claude-37-sonnet', ['getFinancialTransactions'], 1920, 0, 0.0058, '15.0s', '08 Jun 09:22:40', '08 Jun 09:22:55', 'Find duplicate payments over QAR 50,000 this month.', "Client tool 'getFinancialTransactions' returned a schema-incompatible result (missing 'summary'). Run failed validation."],
            ['run_e07b29', 'ag_ops_summary', 'MOP', 'prj_mop_ops', 'r.saleh', 'completed', 'gpt-4o', ['getOperationalRecords'], 2980, 720, 0.0146, '3.9s', '08 Jun 09:15:02', '08 Jun 09:15:06', 'Give me a berth utilization summary for Hamad Port today.', null],
            ['run_f51c84', 'ag_doc_review', 'MOP', 'prj_mop_docs', 'n.adel', 'completed', 'claude-37-sonnet', ['summarizeUploadedDocument', 'searchPolicyDocuments'], 5210, 1340, 0.0357, '7.4s', '08 Jun 08:58:31', '08 Jun 08:58:38', 'Review this bill of lading for missing fields and policy compliance.', null],
            ['run_b88a02', 'ag_customer_trend', 'CSP', 'prj_csp_trend', 's.diab', 'running', 'gpt-4o', ['getCustomerInteractions'], 1620, 0, 0.004, '—', '08 Jun 09:45:10', '—', 'Compare sentiment between phone and email channels this month.', null],
            ['run_19fd47', 'ag_approval_review', 'FWS', 'prj_fws_appr', 'a.rahman', 'expired', 'claude-37-sonnet', ['getPendingApprovals'], 1240, 0, 0.0037, '60.0s', '08 Jun 08:40:00', '08 Jun 08:41:00', 'Review approvals for the procurement queue.', 'Pending client-side tool execution exceeded the 60s timeout. Run expired.'],
            ['run_2c6e91', 'ag_procure_insight', 'PMA', 'prj_pma_insight', 'h.karam', 'completed', 'gpt-4o-mini', ['getProcurementRequests', 'webSearch'], 2210, 540, 0.0006, '5.2s', '08 Jun 08:31:18', '08 Jun 08:31:23', 'Summarize open requisitions for the Engineering department.', null],
            ['run_5a0db3', 'ag_fin_exception', 'FWS', 'prj_fws_appr', 't.nabil', 'completed', 'claude-37-sonnet', ['getFinancialTransactions', 'notifyWorkflowOwner'], 3340, 910, 0.0237, '5.6s', '08 Jun 08:12:44', '08 Jun 08:12:50', 'Any unusual vendor payments in the last 7 days?', null],
            ['run_44b7ec', 'ag_ops_summary', 'MOP', 'prj_mop_ops', 'k.almansoori', 'cancelled', 'gpt-4o', [], 480, 0, 0.0012, '1.1s', '08 Jun 07:55:09', '08 Jun 07:55:10', 'Summarize operations — cancelled by caller.', null],
        ];

        foreach ($rows as [$slug, $agentSlug, $appSlug, $projectSlug, $caller, $status, $llmSlug, $toolSlugs, $tokensIn, $tokensOut, $cost, $latency, $started, $completed, $input, $error]) {
            $run = AgentRun::updateOrCreate(['slug' => $slug], [
                'agent_id' => $agents[$agentSlug]->id,
                'application_id' => $apps[$appSlug]->id,
                'project_id' => $projects[$projectSlug]->id,
                'llm_provider_id' => $llms[$llmSlug]->id,
                'caller' => $caller,
                'status' => $status,
                'tokens_in' => $tokensIn,
                'tokens_out' => $tokensOut,
                'cost' => $cost,
                'latency_ms' => $this->latencyToMs($latency),
                'tools' => $toolSlugs,
                'input' => $input,
                'error' => $error,
                'started_at' => $this->runTimeToCarbon($started),
                'completed_at' => $this->runTimeToCarbon($completed),
            ]);

            $this->seedRunDetail($run, $toolSlugs, $tools, $status);
        }
    }

    /**
     * Seed the tool calls and trace events for a run so the run detail screen
     * has a real execution trace to render.
     *
     * @param  array<int, string>  $toolSlugs
     * @param  array<string, ToolContract>  $tools
     */
    private function seedRunDetail(AgentRun $run, array $toolSlugs, array $tools, string $status): void
    {
        $run->toolCalls()->delete();
        $run->traceEvents()->delete();

        $startedAt = $run->started_at ?? now();
        $sequence = 0;
        $callStatus = $status === 'failed' ? 'failed' : 'completed';

        foreach ($toolSlugs as $i => $toolSlug) {
            $tool = $tools[$toolSlug] ?? null;
            $run->toolCalls()->create([
                'tool_contract_id' => $tool?->id,
                'tool_name' => $toolSlug,
                'status' => $i === count($toolSlugs) - 1 ? $callStatus : 'completed',
                'arguments' => [],
                'result' => $callStatus === 'failed' && $i === count($toolSlugs) - 1 ? null : ['ok' => true],
                'execution_mode' => $tool?->execution_mode->value,
                'sequence' => $i,
                'requested_at' => $startedAt->copy()->addSeconds($i + 1),
                'completed_at' => $startedAt->copy()->addSeconds($i + 2),
            ]);
        }

        $trace = [
            [TraceEventType::RunRequested, 'Run requested by '.$run->caller],
            [TraceEventType::CallerAuthenticated, 'Caller authenticated against application credentials'],
            [TraceEventType::ModelSelected, 'Model selected for the run'],
            [TraceEventType::PromptPrepared, 'System prompt and context prepared'],
        ];

        foreach ($toolSlugs as $toolSlug) {
            $trace[] = [TraceEventType::ToolRequired, "Tool required: {$toolSlug}"];
            $trace[] = [TraceEventType::ToolResultReceived, "Tool result received: {$toolSlug}"];
        }

        if ($status === 'completed') {
            $trace[] = [TraceEventType::Completed, 'Final response generated'];
        } elseif ($status === 'failed' || $status === 'expired') {
            $trace[] = [TraceEventType::Failed, $run->error ?? 'Run failed'];
        }

        foreach ($trace as [$type, $message]) {
            $run->traceEvents()->create([
                'type' => $type,
                'message' => $message,
                'sequence' => $sequence,
                'occurred_at' => $startedAt->copy()->addSeconds($sequence),
            ]);
            $sequence++;
        }
    }

    /**
     * Assign the demo user MAAC project roles for a few projects so the console
     * reflects member assignments and the RBAC concepts.
     *
     * @param  array<string, Project>  $projects
     */
    private function seedProjectMembers(array $projects, User $user): void
    {
        $assignments = [
            'prj_mop_ops' => MaacRole::ProjectOwner,
            'prj_fws_appr' => MaacRole::Developer,
            'prj_csp_trend' => MaacRole::Viewer,
        ];

        foreach ($assignments as $slug => $role) {
            $projects[$slug]->members()->syncWithoutDetaching([
                $user->id => ['maac_role' => $role->value],
            ]);
        }
    }

    /**
     * Seed a handful of historical audit events backing the governance trail.
     *
     * @param  array<string, Application>  $apps
     * @param  array<string, Agent>  $agents
     */
    private function seedAuditEvents(Team $team, array $apps, array $agents, User $user): void
    {
        foreach ($apps as $app) {
            AuditEvent::updateOrCreate(
                ['team_id' => $team->id, 'action' => 'application.registered', 'auditable_type' => Application::class, 'auditable_id' => $app->id],
                ['actor_user_id' => $user->id, 'actor_label' => $user->name, 'environment' => $app->environment->value, 'metadata' => ['name' => $app->name]],
            );
        }

        $vms = $apps['VMS'];
        AuditEvent::updateOrCreate(
            ['team_id' => $team->id, 'action' => 'credential.revoked', 'auditable_type' => Application::class, 'auditable_id' => $vms->id],
            ['actor_user_id' => $user->id, 'actor_label' => $user->name, 'environment' => $vms->environment->value, 'metadata' => ['reason' => 'Revoked by admin']],
        );

        foreach ($agents as $agent) {
            if ($agent->status->isPublished()) {
                AuditEvent::updateOrCreate(
                    ['team_id' => $team->id, 'action' => 'agent.published', 'auditable_type' => Agent::class, 'auditable_id' => $agent->id],
                    ['actor_user_id' => $user->id, 'actor_label' => $user->name, 'metadata' => ['version' => $agent->version]],
                );
            }
        }
    }

    /**
     * Convert a relative time label ("2 min ago", "1 hr ago", "3 days ago") to a
     * concrete timestamp. Returns null for the placeholder "—".
     */
    private function relativeToCarbon(?string $label): ?CarbonInterface
    {
        if ($label === null || $label === '—') {
            return null;
        }

        if (preg_match('/^(\d+)\s+(min|hr|day|days|hour|hours|minutes?)/', $label, $m)) {
            $value = (int) $m[1];

            return match (true) {
                str_starts_with($m[2], 'min') => now()->subMinutes($value),
                str_starts_with($m[2], 'h') => now()->subHours($value),
                default => now()->subDays($value),
            };
        }

        return now();
    }

    /**
     * Parse a fixture run timestamp ("08 Jun 09:41:22") to a 2026 Carbon
     * instance. Returns null for the placeholder "—".
     */
    private function runTimeToCarbon(?string $value): ?CarbonInterface
    {
        if ($value === null || $value === '—') {
            return null;
        }

        return Carbon::createFromFormat('d M H:i:s', $value)->setYear(2026);
    }

    /**
     * Parse a fixture latency label ("4.2s") to milliseconds. Returns null for
     * the placeholder "—".
     */
    private function latencyToMs(?string $value): ?int
    {
        if ($value === null || $value === '—') {
            return null;
        }

        return (int) round(((float) rtrim($value, 's')) * 1000);
    }
}
