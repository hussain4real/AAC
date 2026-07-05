<?php

namespace App\Console\Commands;

use App\Enums\AgentStatus;
use App\Enums\AppStatus;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Enums\LlmStatus;
use App\Enums\ProjectStatus;
use App\Enums\Sensitivity;
use App\Enums\ToolScope;
use App\Enums\VaultSecretKind;
use App\Models\Agent;
use App\Models\Application;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\Team;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use App\Support\Secrets\Contracts\SecretVault;
use Illuminate\Console\Command;

/**
 * Provisions a self-contained, idempotent setup for live runtime smoke testing
 * against a real OpenAI model: an approved OpenAI gpt-5.4 catalog entry, a
 * published no-tools agent, and a published agent wired to the MAACC-hosted `sum`
 * tool. When an API key is supplied it is stored in the secrets vault and bound
 * to the model, so the runtime resolves the key from the vault on the next run.
 *
 * The created agents surface in the console playground for the chosen team, so a
 * console user can run them against the live provider end-to-end.
 */
class ProvisionOpenAiSmoke extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'maacc:openai-smoke
        {--team= : Team slug (defaults to the first team)}
        {--key= : OpenAI API key to store in the vault and bind to the model}
        {--model=gpt-5.4 : The OpenAI model code the agents run on}
        {--environment=production : The environment the model and agents are approved for}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Provision an OpenAI gpt-5.4 model plus a no-tools and a hosted-tool agent for live runtime smoke testing, optionally storing the key in the vault';

    /**
     * Execute the console command.
     */
    public function handle(SecretVault $vault): int
    {
        $team = $this->resolveTeam();

        if (! $team instanceof Team) {
            $this->error('No team found. Seed a team first or pass --team=<slug>.');

            return self::FAILURE;
        }

        $environment = Environment::tryFrom((string) $this->option('environment')) ?? Environment::Production;
        $model = (string) $this->option('model');

        $provider = $this->ensureProvider($team, $environment, $model);
        $application = $this->ensureApplication($team, $environment);
        $project = $this->ensureProject($application, $environment);
        $vesselTool = $this->ensureVesselStatusTool($team, $application);

        $plainAgent = $this->ensureAgent(
            $project,
            $provider,
            'gpt54-smoke',
            'GPT-5.4 Smoke Agent',
            'You are a helpful assistant for Milaha. Answer the user clearly and concisely.',
        );

        $toolAgent = $this->ensureAgent(
            $project,
            $provider,
            'gpt54-tool',
            'GPT-5.4 Tool Agent',
            'You are a Milaha fleet operations assistant. You have NO direct knowledge of live vessel positions, statuses, or ETAs — that data lives only in the fleet system, reachable through the `vessel_status` tool. Whenever the user asks about a vessel\'s status, port, or ETA, you MUST call the `vessel_status` tool with the vessel name and answer strictly from what it returns. Never guess or invent vessel data.',
        );

        $this->assignTool($toolAgent, $vesselTool);

        $key = $this->option('key');

        if (is_string($key) && $key !== '') {
            $secret = $vault->store($team, VaultSecretKind::LlmKey->reference($provider->slug), 'OpenAI '.$model.' key', VaultSecretKind::LlmKey, $key);
            $provider->update(['vault_secret_id' => $secret->id]);
            $this->info('Stored the OpenAI key in the vault and bound it to the model.');
        }

        $this->newLine();
        $this->info('Provisioned OpenAI smoke setup for team ['.$team->slug.'].');
        $this->line('  Model            : '.$provider->name.' (code '.$provider->code.', driver '.$provider->driver().')');
        $this->line('  Vault key bound  : '.($provider->vault_secret_id !== null ? 'yes' : 'no (run again with --key=… to bind one)'));
        $this->line('  No-tools agent   : '.$plainAgent->agent_slug);
        $this->line('  Hosted-tool agent: '.$toolAgent->agent_slug.' (vessel_status)');
        $this->line('  Playground       : /'.$team->slug.'/playground');

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
     * Ensure the OpenAI model catalog entry exists, approved for the environment.
     */
    private function ensureProvider(Team $team, Environment $environment, string $model): LlmProvider
    {
        // Seed the per-1M fallback rates from the central pricing catalog so the
        // figures live in exactly one reviewed place (config/maacc.php pricing).
        $catalog = config('maacc.pricing.models');
        $rates = (is_array($catalog) ? ($catalog[$model] ?? null) : null) ?? ['input' => 0.0, 'output' => 0.0];

        return LlmProvider::query()->updateOrCreate(
            ['team_id' => $team->id, 'slug' => 'openai-'.str_replace('.', '-', $model)],
            [
                'name' => 'OpenAI '.$model,
                'code' => $model,
                'provider' => 'OpenAI',
                'context_window' => '400K',
                'input_cost' => $rates['input'],
                'output_cost' => $rates['output'],
                'sensitivity' => Sensitivity::Internal,
                'environments' => [$environment->value],
                'status' => LlmStatus::Approved,
                'usage_pct' => 0,
                'runs_count' => 0,
                'note' => 'Live runtime smoke-test model.',
            ],
        );
    }

    /**
     * Ensure the smoke application exists for the team.
     */
    private function ensureApplication(Team $team, Environment $environment): Application
    {
        return Application::query()->updateOrCreate(
            ['team_id' => $team->id, 'slug' => 'playground-smoke'],
            [
                'code' => 'playground-smoke',
                'name' => 'Playground Smoke',
                'department' => 'Marine & Technical Services',
                'owner_name' => 'Platform Team',
                'owner_email' => 'platform@milaha.test',
                'environment' => $environment,
                'status' => AppStatus::Active,
                'stack' => 'Laravel · PHP 8.5',
                'description' => 'Live runtime smoke-test application.',
                'region' => 'Qatar — Doha DC',
            ],
        );
    }

    /**
     * Ensure the smoke project exists under the application.
     */
    private function ensureProject(Application $application, Environment $environment): Project
    {
        return Project::query()->updateOrCreate(
            ['application_id' => $application->id, 'slug' => 'playground-smoke-project'],
            [
                'name' => 'Playground Smoke Project',
                'environment' => $environment,
                'description' => 'Live runtime smoke-test project.',
                'business_owner' => 'Platform Team',
                'technical_owner' => 'Platform Team',
                'status' => ProjectStatus::Active,
            ],
        );
    }

    /**
     * Ensure the MAACC-hosted `vessel_status` tool contract exists. A real model
     * cannot know live vessel data, so it must call this tool — which makes it a
     * reliable demonstration of the tool-call protocol end-to-end.
     */
    private function ensureVesselStatusTool(Team $team, Application $application): ToolContract
    {
        return ToolContract::query()->updateOrCreate(
            ['team_id' => $team->id, 'slug' => 'vessel_status'],
            [
                'application_id' => $application->id,
                'name' => 'Vessel Status',
                'description' => 'Returns the live status, port, and ETA for a vessel from the fleet system.',
                'scope' => ToolScope::Project,
                'execution_mode' => ExecMode::Hosted,
                'sensitivity' => Sensitivity::Internal,
                'requires_approval' => false,
                'status' => 'Active',
                'implementation_status' => ImplStatus::Implemented,
                'timeout_seconds' => 15,
                'max_payload_kb' => 64,
                'input_schema' => ['vessel' => 'string'],
                'output_schema' => ['vessel' => 'string', 'status' => 'string', 'port' => 'string', 'eta' => 'string'],
                'version' => '1.0.0',
            ],
        );
    }

    /**
     * Ensure a published agent on the model exists for the project.
     */
    private function ensureAgent(Project $project, LlmProvider $provider, string $slug, string $name, string $systemPrompt): Agent
    {
        return Agent::query()->updateOrCreate(
            ['project_id' => $project->id, 'agent_slug' => $slug],
            [
                'llm_provider_id' => $provider->id,
                'slug' => $slug,
                'name' => $name,
                'version' => 'v1',
                'status' => AgentStatus::Published,
                'sensitivity' => Sensitivity::Internal,
                'requires_runtime_approval' => false,
                'system_prompt' => $systemPrompt,
                'temperature' => 1.0,
                'max_tokens' => 1024,
                'description' => 'Live runtime smoke-test agent.',
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
