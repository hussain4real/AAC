<?php

namespace Database\Seeders;

use App\Enums\AgentStatus;
use App\Enums\AppStatus;
use App\Enums\CredentialStatus;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Enums\LlmStatus;
use App\Enums\ProjectStatus;
use App\Enums\Sensitivity;
use App\Enums\ToolScope;
use App\Models\Agent;
use App\Models\Application;
use App\Models\Credential;
use App\Models\LlmProvider;
use App\Models\Project;
use App\Models\Team;
use App\Models\ToolAssignment;
use App\Models\ToolContract;
use App\Models\User;
use App\Support\Runtime\DeterministicLlmRouter;
use App\Support\Sdk\SdkClientManager;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Seeds the canonical MAAC end-to-end scenario with stable identifiers so local
 * and CI runs exercise the same team, application, credential, model, agent, and
 * client-side tool. This is the deterministic fixture behind the Phase 6A
 * validation harness: a published agent with one assigned client tool that the
 * runtime pauses on, plus a credential backed by a Passport client for SDK token
 * exchange. Idempotent — keyed on the public slug constants below.
 *
 * Pair it with `MAAC_LLM_DRIVER=fake` (the {@see DeterministicLlmRouter})
 * to drive a complete run without any external model provider.
 */
class MaacE2ESeeder extends Seeder
{
    public const USER_EMAIL = 'e2e@milaha.com';

    public const APP_SLUG = 'e2e-cargo-insights';

    public const APP_CODE = 'E2E';

    public const PROVIDER_SLUG = 'e2e-deterministic-model';

    public const PROJECT_SLUG = 'e2e-cargo-project';

    public const TOOL_SLUG = 'e2e-fetch-records';

    public const AGENT_SLUG = 'e2e-ops-agent';

    public const CREDENTIAL_LABEL = 'E2E validation credentials';

    public const ENVIRONMENT = Environment::Production;

    public function __construct(private readonly SdkClientManager $sdkClients) {}

    /**
     * Seed the canonical end-to-end scenario.
     */
    public function run(): void
    {
        $user = User::firstWhere('email', self::USER_EMAIL)
            ?? User::factory()->create(['name' => 'E2E Runner', 'email' => self::USER_EMAIL]);

        /** @var Team $team */
        $team = $user->currentTeam ?? $user->personalTeam();

        $application = Application::updateOrCreate(['slug' => self::APP_SLUG], [
            'team_id' => $team->id,
            'code' => self::APP_CODE,
            'name' => 'E2E Cargo Insights',
            'department' => 'Platform Validation',
            'owner_name' => 'E2E Runner',
            'owner_email' => self::USER_EMAIL,
            'environment' => self::ENVIRONMENT->value,
            'status' => AppStatus::Active->value,
            'stack' => 'Laravel · PHP 8.4',
            'description' => 'Deterministic application used by the MAAC end-to-end validation harness.',
            'region' => 'Qatar — Doha DC',
        ]);

        $provider = LlmProvider::updateOrCreate(['slug' => self::PROVIDER_SLUG], [
            'team_id' => $team->id,
            'name' => 'E2E Deterministic Model',
            'code' => 'fake/e2e-deterministic',
            'provider' => 'MAAC Deterministic',
            'context_window' => '128K',
            'input_cost' => 1.0,
            'output_cost' => 2.0,
            'sensitivity' => Sensitivity::Internal->value,
            'environments' => [self::ENVIRONMENT->value],
            'status' => LlmStatus::Approved->value,
            'note' => 'Approved only for the validation harness.',
        ]);

        $project = Project::updateOrCreate(['slug' => self::PROJECT_SLUG], [
            'application_id' => $application->id,
            'name' => 'E2E Cargo Project',
            'environment' => self::ENVIRONMENT->value,
            'description' => 'Houses the validation agent and its client-side tool.',
            'business_owner' => 'E2E Runner',
            'technical_owner' => 'E2E Runner',
            'status' => ProjectStatus::Active->value,
        ]);
        $project->llmProviders()->syncWithoutDetaching([$provider->id]);

        $tool = ToolContract::updateOrCreate(['slug' => self::TOOL_SLUG], [
            'team_id' => $team->id,
            'application_id' => $application->id,
            'name' => 'E2E Fetch Records',
            'description' => 'Returns operational records for the validation harness.',
            'scope' => ToolScope::Agent->value,
            'execution_mode' => ExecMode::Client->value,
            'sensitivity' => Sensitivity::Internal->value,
            'requires_approval' => false,
            'status' => 'Active',
            'implementation_status' => ImplStatus::Required->value,
            'timeout_seconds' => 15,
            'max_payload_kb' => 256,
            'input_schema' => ['query' => 'string'],
            'output_schema' => ['records' => 'array', 'total' => 'number'],
            'version' => '1.0.0',
        ]);

        $agent = Agent::updateOrCreate(['slug' => self::AGENT_SLUG], [
            'project_id' => $project->id,
            'llm_provider_id' => $provider->id,
            'agent_slug' => self::AGENT_SLUG,
            'name' => 'E2E Operations Agent',
            'version' => 'v1',
            'status' => AgentStatus::Published->value,
            'sensitivity' => Sensitivity::Internal->value,
            'system_prompt' => 'You summarize operational records for the validation harness.',
            'temperature' => 0.2,
            'max_tokens' => 1200,
            'description' => 'Published agent exercised by the end-to-end validation harness.',
            'published_at' => Carbon::now(),
        ]);

        ToolAssignment::updateOrCreate(
            ['tool_contract_id' => $tool->id, 'agent_id' => $agent->id],
            ['scope' => ToolScope::Agent->value, 'project_id' => null, 'environment' => null],
        );

        $this->seedCredential($application, $user);
    }

    /**
     * Provision a Passport-backed credential once, surfacing the one-time secret
     * to the console (so a developer can drive the SDK smoke against it).
     */
    private function seedCredential(Application $application, User $user): void
    {
        $existing = Credential::query()
            ->where('application_id', $application->id)
            ->where('label', self::CREDENTIAL_LABEL)
            ->first();

        if ($existing !== null) {
            return;
        }

        $credential = new Credential([
            'application_id' => $application->id,
            'environment' => self::ENVIRONMENT->value,
            'label' => self::CREDENTIAL_LABEL,
            'status' => CredentialStatus::Active->value,
            'created_by' => $user->id,
        ]);

        $secret = $this->sdkClients->provision(
            $credential,
            $application->name.' — '.self::ENVIRONMENT->label(),
        );
        $credential->save();

        $this->command->info('MAAC E2E credential provisioned for the SDK smoke:');
        $this->command->info('  client_id:     '.$credential->client_id);
        $this->command->info('  client_secret: '.$secret);
    }
}
