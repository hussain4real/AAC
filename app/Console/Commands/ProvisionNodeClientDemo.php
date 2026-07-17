<?php

namespace App\Console\Commands;

use App\Actions\Maacc\CreateCredential;
use App\Enums\AgentStatus;
use App\Enums\AppStatus;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Enums\ProjectStatus;
use App\Enums\Sensitivity;
use App\Enums\ToolScope;
use App\Models\Agent;
use App\Models\Application;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\Team;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Provisions the server side for the standalone Node SDK test client: an
 * application, a Passport client_credentials credential (whose one-time secret
 * is printed), and a published gpt-5.4 agent wired to a CLIENT-side tool whose
 * data lives only in the consuming app. The agent's prompt forces the model to
 * call the tool, so a real run pauses for client-side execution — which is what
 * the Node app implements and resumes.
 *
 * Requires a vault-keyed model from {@see ProvisionOpenAiSmoke} (`maacc:openai-smoke`).
 */
class ProvisionNodeClientDemo extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maacc:node-client-demo
        {--team= : Team slug (defaults to the first team)}
        {--model=gpt-5.4 : The OpenAI model code the agent runs on}
        {--environment=production : The environment for the app, agent, and credential}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision an application, a client_credentials credential, and a published gpt-5.4 agent with a client-side tool for the standalone Node SDK test client';

    /**
     * Execute the console command.
     */
    public function handle(CreateCredential $credentials): int
    {
        $team = $this->resolveTeam();

        if (! $team instanceof Team) {
            $this->error('No team found. Seed a team first or pass --team=<slug>.');

            return self::FAILURE;
        }

        $creator = $team->owner() ?? $team->members()->first();

        if (! $creator instanceof User) {
            $this->error('The team has no member to own the credential.');

            return self::FAILURE;
        }

        $environment = Environment::tryFrom((string) $this->option('environment')) ?? Environment::Production;
        $model = (string) $this->option('model');

        $provider = LlmProvider::query()
            ->where('team_id', $team->id)
            ->where('code', $model)
            ->whereNotNull('vault_secret_id')
            ->first();

        if (! $provider instanceof LlmProvider) {
            $this->error("No vault-keyed [{$model}] model found for team [{$team->slug}]. Run: php artisan maacc:openai-smoke --team={$team->slug} --key=… first.");

            return self::FAILURE;
        }

        $application = $this->ensureApplication($team, $environment);
        $project = $this->ensureProject($application, $environment);
        $project->llmProviders()->syncWithoutDetaching([$provider->id]);
        $tool = $this->ensureClientTool($team, $application);
        $agent = $this->ensureAgent($project, $provider);
        $this->assignTool($agent, $tool);

        $secret = $credentials->handle($application, $creator, [
            'environment' => $environment->value,
            'label' => 'Node SDK test client',
        ]);

        $this->newLine();
        $this->info('Provisioned the Node SDK test client for team ['.$team->slug.'].');
        $this->line('  MAACC_BASE_URL      : '.rtrim((string) config('app.url'), '/'));
        $this->line('  MAACC_CLIENT_ID     : '.$secret->credential->client_id);
        $this->line('  MAACC_CLIENT_SECRET : '.$secret->plainSecret.'   (shown once)');
        $this->line('  MAACC_AGENT_SLUG    : '.$agent->agent_slug);
        $this->line('  MAACC_TOOL_SLUG     : '.$tool->slug.' (client-side, '.ImplStatus::Required->label().')');

        return self::SUCCESS;
    }

    /**
     * Resolve the target team from the --team option or fall back to the first.
     */
    private function resolveTeam(): ?Team
    {
        $slug = $this->option('team');

        if (is_string($slug) && $slug !== '') {
            return Team::query()->where('slug', $slug)->first();
        }

        return Team::query()->oldest('id')->first();
    }

    /**
     * Ensure the Node test client application exists.
     */
    private function ensureApplication(Team $team, Environment $environment): Application
    {
        return Application::query()->updateOrCreate(
            ['team_id' => $team->id, 'slug' => 'node-test-client'],
            [
                'code' => 'node-test-client',
                'name' => 'Node Test Client',
                'department' => 'Marine & Technical Services',
                'owner_name' => 'Platform Team',
                'owner_email' => 'platform@milaha.test',
                'environment' => $environment,
                'status' => AppStatus::Active,
                'stack' => 'Node.js · TypeScript',
                'description' => 'External Node integration exercising the MAACC SDK and client-side tools.',
                'region' => 'Qatar — Doha DC',
            ],
        );
    }

    /**
     * Ensure the project under the Node test client application exists.
     */
    private function ensureProject(Application $application, Environment $environment): Project
    {
        return Project::query()->updateOrCreate(
            ['application_id' => $application->id, 'slug' => 'node-test-client-project'],
            [
                'name' => 'Node Test Client Project',
                'environment' => $environment,
                'description' => 'Client-side tool integration project.',
                'business_owner' => 'Platform Team',
                'technical_owner' => 'Platform Team',
                'status' => ProjectStatus::Active,
            ],
        );
    }

    /**
     * Ensure the client-side `fetch_port_records` tool contract exists. Its data
     * lives only in the consuming Node app, so the model has to call it.
     */
    private function ensureClientTool(Team $team, Application $application): ToolContract
    {
        return ToolContract::query()->updateOrCreate(
            ['team_id' => $team->id, 'application_id' => $application->id, 'slug' => 'fetch_port_records'],
            [
                'name' => 'Fetch Port Records',
                'description' => 'Returns current port operational records (gate queues, berth and crane status) from the operator\'s local system.',
                'scope' => ToolScope::Project,
                'execution_mode' => ExecMode::Client,
                'sensitivity' => Sensitivity::Internal,
                'requires_approval' => false,
                'status' => 'Active',
                'implementation_status' => ImplStatus::Required,
                'timeout_seconds' => 15,
                'max_payload_kb' => 256,
                'input_schema' => ['query' => 'string?'],
                'output_schema' => ['records' => 'array', 'total' => 'number'],
                'version' => '1.0.0',
            ],
        );
    }

    /**
     * Ensure the published agent that uses the client-side tool exists.
     */
    private function ensureAgent(Project $project, LlmProvider $provider): Agent
    {
        return Agent::query()->updateOrCreate(
            ['project_id' => $project->id, 'agent_slug' => 'node-port-ops'],
            [
                'llm_provider_id' => $provider->id,
                'slug' => 'node-port-ops',
                'name' => 'Node Port Ops Agent',
                'version' => 'v1',
                'status' => AgentStatus::Published,
                'sensitivity' => Sensitivity::Internal,
                'requires_runtime_approval' => false,
                'system_prompt' => 'You are a Milaha port operations assistant. You have NO direct access to live port, berth, gate, or crane status — that data exists only in the operator\'s local system, reachable through the `fetch_port_records` tool. Whenever the user asks about current port operations, you MUST call `fetch_port_records` and answer strictly from what it returns. Never invent or assume operational data.',
                'temperature' => 1.0,
                'max_tokens' => 1024,
                'description' => 'Summarizes live port operations via a client-side tool.',
                'success_rate' => 100,
                'runs_7d' => 0,
                'published_at' => now(),
            ],
        );
    }

    /**
     * Ensure the given tool is assigned to the agent.
     */
    private function assignTool(Agent $agent, ToolContract $tool): void
    {
        ToolAssignment::query()->firstOrCreate(
            ['agent_id' => $agent->id, 'tool_contract_id' => $tool->id],
            ['scope' => ToolScope::Agent],
        );
    }
}
