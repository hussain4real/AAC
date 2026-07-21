<?php

use App\Actions\Maacc\ApproveApprovalRequest;
use App\Actions\Maacc\PublishAgent;
use App\Actions\Maacc\PublishLlmProvider;
use App\Actions\Maacc\RejectApprovalRequest;
use App\Actions\Maacc\UpdateQuotaLimit;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\AppStatus;
use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\LlmStatus;
use App\Enums\MaaccRole;
use App\Enums\PayloadHandling;
use App\Enums\ProjectStatus;
use App\Enums\QuotaScope;
use App\Enums\RunStatus;
use App\Enums\ToolCallStatus;
use App\Enums\ToolScope;
use App\Exceptions\ApprovalBlockedException;
use App\Exceptions\Sdk\RuntimeRequestException;
use App\Http\Middleware\EnsureCurrentTeamResourceOwnership;
use App\Models\AgentRun;
use App\Models\Application;
use App\Models\ApprovalRequest;
use App\Models\Credential;
use App\Models\DataSource;
use App\Models\EvaluationDataset;
use App\Models\KnowledgeSource;
use App\Models\LlmProvider;
use App\Models\McpConnector;
use App\Models\PlatformAccessGrant;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\QuotaLimit;
use App\Models\SsoConnection;
use App\Models\ToolCall;
use App\Models\ToolContract;
use App\Models\User;
use App\Models\WebhookEndpoint;
use App\Support\EnterpriseReadiness;
use App\Support\Governance\AgentReadinessGate;
use App\Support\Governance\ApprovalGate;
use App\Support\Governance\ApprovalManager;
use App\Support\Governance\ApprovalVersion;
use App\Support\Governance\TenantRelationshipGuard;
use App\Support\Runtime\AgentRunner;
use App\Support\Runtime\RequestScopedAiProviderFactory;
use App\Support\Sdk\SdkClientManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\mock;

test('enterprise containment identifies its accountable change owner and disables real sensitive data', function () {
    expect(app(EnterpriseReadiness::class)->sharedState())->toMatchArray([
        'status' => 'non_enterprise',
        'registrationEnabled' => false,
        'teamCreationEnabled' => false,
        'realSensitiveDataEnabled' => false,
        'changeOwner' => 'Aminu Hussain',
    ]);
});

test('phase one value objects and controlled errors expose stable public representations', function () {
    expect(PayloadHandling::Store->label())->toBe('Store')
        ->and(PayloadHandling::Mask->label())->toBe('Mask')
        ->and(PayloadHandling::Exclude->label())->toBe('Exclude');

    $request = Request::create('/api/v1/test');
    $request->attributes->set('correlation_id', 'corr_existing');
    $response = RuntimeRequestException::invalidToolResult(['result must be an object'])->render($request);

    expect($response->getStatusCode())->toBe(422)
        ->and($response->headers->get('X-Correlation-ID'))->toBe('corr_existing')
        ->and($response->getData(true))->toMatchArray([
            'error' => 'invalid_tool_result',
            'correlation_id' => 'corr_existing',
        ]);
});

test('a verified model publisher applies the approved catalog status', function () {
    [, $team] = ownerAndTeam();
    $provider = LlmProvider::factory()->for($team)->draft()->create();

    $published = app(PublishLlmProvider::class)->handle($provider);

    expect($published->status)->toBe(LlmStatus::Approved)
        ->and($provider->fresh()->status)->toBe(LlmStatus::Approved);
});

test('direct agent publication reports readiness blockers', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $agent->project->application->update(['status' => 'suspended']);

    expect(fn () => app(PublishAgent::class)->handle($agent, User::factory()->create()))
        ->toThrow(ApprovalBlockedException::class);
});

test('approval effects fail closed when a request resolves the wrong subject type', function () {
    [, $team] = ownerAndTeam();
    $decider = User::factory()->create();
    $tool = ToolContract::factory()->for($team)->create(['status' => 'Pending']);
    $gate = mock(ApprovalGate::class);
    $gate->shouldReceive('ensureSatisfied')->twice();
    $action = new ApproveApprovalRequest(
        app(PublishAgent::class),
        $gate,
        app(SdkClientManager::class),
    );

    $credentialRequest = ApprovalRequest::factory()->for($team)->for($tool, 'subject')->create([
        'type' => ApprovalType::CredentialChange,
        'metadata' => ['change' => 'creation'],
    ]);
    $modelRequest = ApprovalRequest::factory()->for($team)->for($tool, 'subject')->create([
        'type' => ApprovalType::ModelAccess,
        'environment' => Environment::Production,
    ]);

    $action->handle($credentialRequest, $decider);
    $action->handle($modelRequest, $decider);

    expect($credentialRequest->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($modelRequest->fresh()->status)->toBe(ApprovalStatus::Approved)
        ->and($tool->fresh()->status)->toBe('Pending');
});

test('credential creation rejection revokes the staged client and is idempotent', function () {
    [, $team] = ownerAndTeam();
    $decider = User::factory()->create();
    $credential = Credential::factory()
        ->for(Application::factory()->for($team))
        ->withOauthClient()
        ->create();
    $request = app(ApprovalManager::class)->requestCredentialChange($credential, User::factory()->create(), 'creation');

    $rejected = app(RejectApprovalRequest::class)->handle($request, $decider, 'Rejected fixture');
    $again = app(RejectApprovalRequest::class)->handle($rejected, $decider);

    expect($again->status)->toBe(ApprovalStatus::Rejected)
        ->and($credential->fresh()->status->value)->toBe('revoked')
        ->and($credential->oauthClient->fresh()->revoked)->toBeTrue();
});

test('quota updates can intentionally change scope to a validated local subject', function () {
    [, $team] = ownerAndTeam();
    $application = Application::factory()->for($team)->create();
    $quota = QuotaLimit::factory()->for($team)->create([
        'scope' => QuotaScope::Platform,
        'subject_id' => null,
    ]);

    $updated = app(UpdateQuotaLimit::class)->handle($quota, [
        'scope' => QuotaScope::Application->value,
        'subject_id' => $application->id,
    ]);

    expect($updated->scope)->toBe(QuotaScope::Application)
        ->and($updated->subject_id)->toBe($application->id);
});

test('sdk client rotation updates an existing Passport client and credential hash', function () {
    [, $team] = ownerAndTeam();
    $credential = Credential::factory()
        ->for(Application::factory()->for($team))
        ->withOauthClient()
        ->create();

    $secret = app(SdkClientManager::class)->rotate($credential);

    expect($secret)->not->toBeEmpty()
        ->and(Hash::check($secret, $credential->secret_hash))->toBeTrue();
});

test('sdk client rotation supports credentials that predate Passport linkage', function () {
    [, $team] = ownerAndTeam();
    $credential = Credential::factory()
        ->for(Application::factory()->for($team))
        ->create(['oauth_client_id' => null]);

    $secret = app(SdkClientManager::class)->rotate($credential);

    expect($secret)->not->toBeEmpty()
        ->and(Hash::check($secret, $credential->secret_hash))->toBeTrue();
});

test('request scoped provider construction supports every configured text driver', function (string $driver) {
    $provider = app(RequestScopedAiProviderFactory::class)->make($driver, 'tenant-scoped-key');

    expect($provider)->toBeInstanceOf(TextProvider::class);
})->with([
    'azure',
    'bedrock',
    'deepseek',
    'gemini',
    'groq',
    'mistral',
    'ollama',
    'openrouter',
    'xai',
]);

test('phase one defensive model and route ownership branches fail closed', function () {
    [, $team] = ownerAndTeam();
    $approver = User::factory()->create();
    $connection = SsoConnection::factory()->for($team)->create([
        'approved_by' => $approver->id,
    ]);
    $member = new ProjectMember;
    $member->setRawAttributes(['maacc_role' => MaaccRole::Developer]);
    $globalGrant = PlatformAccessGrant::factory()->create();

    $request = Request::create('/team-boundary');
    $route = new Route('GET', '/team-boundary', fn (): Response => new Response('route'));
    $route->bind($request);
    $route->setParameter('current_team', $team);
    $request->setRouteResolver(fn (): Route => $route);

    $response = app(EnsureCurrentTeamResourceOwnership::class)->handle(
        $request,
        fn (): Response => new Response('allowed'),
    );

    expect($member->maacc_role)->toBe(MaaccRole::Developer)
        ->and($connection->approver->is($approver))->toBeTrue()
        ->and(app(TenantRelationshipGuard::class)->modelBelongsToTeam($globalGrant, $team))->toBeTrue()
        ->and($response->getContent())->toBe('allowed');
});

test('approval gates reject tenant drift and malformed staged subjects', function () {
    [, $team] = ownerAndTeam();
    [, $foreignTeam] = ownerAndTeam();
    $gate = app(ApprovalGate::class);
    $foreignTool = ToolContract::factory()->for($foreignTeam)->create();
    $foreignCredential = Credential::factory()
        ->for(Application::factory()->for($foreignTeam))
        ->create();
    $localCredential = Credential::factory()
        ->for(Application::factory()->for($team))
        ->create();
    $localModel = LlmProvider::factory()->for($team)->create();

    $tenantDrift = ApprovalRequest::factory()->for($team)->for($foreignTool, 'subject')->create([
        'type' => ApprovalType::ToolContract,
    ]);
    $invalidCredentialChange = ApprovalRequest::factory()->for($team)->for($localCredential, 'subject')->create([
        'type' => ApprovalType::CredentialChange,
        'subject_version_hash' => app(ApprovalVersion::class)->credential($localCredential),
        'metadata' => ['change' => 'invalid'],
    ]);
    $invalidModelPromotion = ApprovalRequest::factory()->for($team)->for($localModel, 'subject')->create([
        'type' => ApprovalType::ModelAccess,
        'environment' => null,
    ]);
    $wrongAgentSubject = ApprovalRequest::factory()->for($team)->for($foreignTool, 'subject')->make([
        'type' => ApprovalType::AgentPublication,
    ]);
    $wrongCredentialSubject = ApprovalRequest::factory()->for($team)->for($foreignTool, 'subject')->make([
        'type' => ApprovalType::CredentialChange,
    ]);
    $foreignCredentialRequest = ApprovalRequest::factory()->for($team)->for($foreignCredential, 'subject')->make([
        'type' => ApprovalType::CredentialChange,
    ]);

    $agentBlockers = new ReflectionMethod($gate, 'agentBlockers');
    $credentialBlockers = new ReflectionMethod($gate, 'credentialBlockers');

    expect($gate->blockers($tenantDrift))->toContain('The approval subject does not belong to this tenant.')
        ->and($gate->blockers($invalidCredentialChange))->toContain('The staged credential change is invalid.')
        ->and($gate->blockers($invalidModelPromotion))->toContain('The staged model promotion is invalid.')
        ->and($agentBlockers->invoke($gate, $wrongAgentSubject))->toContain('The approval subject no longer exists.')
        ->and($credentialBlockers->invoke($gate, $wrongCredentialSubject))->toContain('The approval subject no longer exists.')
        ->and($credentialBlockers->invoke($gate, $foreignCredentialRequest))->toContain('The approval subject does not belong to this tenant.');
});

test('agent readiness reports archived projects', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $agent->project->update(['status' => ProjectStatus::Archived]);

    expect(app(AgentReadinessGate::class)->blockers(
        $agent->fresh(),
        $agent->project->application,
        $agent->project->environment,
    ))->toContain('The project is archived.');
});

test('tenant relationship writes reject inactive and cross-scope dependencies', function () {
    [, $team] = ownerAndTeam();
    $guard = app(TenantRelationshipGuard::class);
    $inactiveApplication = Application::factory()->for($team)->create([
        'status' => AppStatus::Suspended,
    ]);
    $application = Application::factory()->for($team)->create();
    $unapprovedProvider = LlmProvider::factory()->for($team)->draft()->create();

    expect(fn () => $guard->assertProjectRelationships($team, [
        'application_id' => $inactiveApplication->id,
    ]))->toThrow(ValidationException::class)
        ->and(fn () => $guard->assertProjectRelationships($team, [
            'application_id' => $application->id,
            'environment' => Environment::Production->value,
            'llm_provider_ids' => [$unapprovedProvider->id],
        ]))->toThrow(ValidationException::class);

    $archivedProject = Project::factory()->for($application)->create([
        'status' => ProjectStatus::Archived,
    ]);

    expect(fn () => $guard->assertAgentRelationships($archivedProject, []))
        ->toThrow(ValidationException::class)
        ->and(fn () => $guard->assertToolContractRelationships($team, [
            'application_id' => $inactiveApplication->id,
        ]))->toThrow(ValidationException::class)
        ->and(fn () => $guard->assertToolContractRelationships($team, [
            'application_id' => $application->id,
            'scope' => ToolScope::Global->value,
        ]))->toThrow(ValidationException::class);
});

test('routing and quota guards cover fallback and invalid lifecycle branches', function () {
    [, $team] = ownerAndTeam();
    [, $foreignTeam] = ownerAndTeam();
    $guard = app(TenantRelationshipGuard::class);
    $agent = maaccAgent($team);

    expect(fn () => $guard->assertRoutingPolicyRelationships($foreignTeam, $agent, []))
        ->toThrow(ValidationException::class);

    $guard->assertRoutingPolicyRelationships($team, $agent, [
        'primary_provider_id' => '',
        'fallback_provider_ids' => [],
    ]);
    $guard->assertRoutingPolicyRelationships($team, $agent, [
        'primary_provider_id' => $agent->llm_provider_id,
        'fallback_provider_ids' => [],
    ]);

    $agent->project->update(['status' => ProjectStatus::Archived]);

    expect(fn () => $guard->assertRoutingPolicyRelationships($team, $agent->fresh(), []))
        ->toThrow(ValidationException::class)
        ->and(fn () => $guard->assertQuotaSubject($team, QuotaScope::Application, null))
        ->toThrow(ValidationException::class);
});

test('route model ownership rejects every supported foreign parent shape', function () {
    [, $team] = ownerAndTeam();
    [, $foreignTeam] = ownerAndTeam();
    $guard = app(TenantRelationshipGuard::class);
    $foreignAgent = maaccAgent($foreignTeam);
    $foreignKnowledge = KnowledgeSource::factory()->for($foreignTeam)->create();
    $foreignDataset = EvaluationDataset::factory()->for($foreignTeam)->create();
    $foreignEndpoint = WebhookEndpoint::factory()
        ->for(Application::factory()->for($foreignTeam))
        ->create();
    $foreignApplication = Application::factory()->for($foreignTeam)->create();
    $foreignTool = ToolContract::factory()->for($foreignTeam)->create();

    $withAttribute = static function (string $attribute, string $value): Model {
        $model = new class extends Model {};
        $model->setRawAttributes([$attribute => $value]);

        return $model;
    };

    $invalidQuota = QuotaLimit::factory()->for($team)->make([
        'scope' => QuotaScope::Application,
        'subject_id' => $foreignApplication->id,
    ]);
    $missingQuotaSubject = QuotaLimit::factory()->for($team)->make([
        'scope' => QuotaScope::Application,
        'subject_id' => null,
    ]);
    $foreignApproval = ApprovalRequest::factory()->for($team)->for($foreignTool, 'subject')->make();

    expect($guard->modelBelongsToTeam($withAttribute('agent_id', $foreignAgent->id), $team))->toBeFalse()
        ->and($guard->modelBelongsToTeam($withAttribute('knowledge_source_id', $foreignKnowledge->id), $team))->toBeFalse()
        ->and($guard->modelBelongsToTeam($withAttribute('evaluation_dataset_id', $foreignDataset->id), $team))->toBeFalse()
        ->and($guard->modelBelongsToTeam($withAttribute('webhook_endpoint_id', $foreignEndpoint->id), $team))->toBeFalse()
        ->and($guard->modelBelongsToTeam($invalidQuota, $team))->toBeFalse()
        ->and($guard->modelBelongsToTeam($missingQuotaSubject, $team))->toBeFalse()
        ->and($guard->modelBelongsToTeam($foreignApproval, $team))->toBeFalse();
});

test('tool backing-resource guards cover missing invalid and application-scoped resources', function () {
    [, $team] = ownerAndTeam();
    [, $foreignTeam] = ownerAndTeam();
    $guard = app(TenantRelationshipGuard::class);
    $agent = maaccAgent($team);
    $project = $agent->project;
    $application = $project->application;
    $inactiveTool = ToolContract::factory()->for($team)->for($application)->create([
        'status' => 'Draft',
    ]);

    expect($guard->toolIsEligibleForProject($inactiveTool, $project))->toBeFalse();

    $missingBackedTool = new ToolContract;
    $missingBackedTool->setRawAttributes([
        'team_id' => $team->id,
        'application_id' => $application->id,
        'status' => 'Active',
        'scope' => ToolScope::Project->value,
        'mcp_connector_id' => (string) Str::uuid(),
        'knowledge_source_id' => null,
        'data_source_id' => null,
    ]);

    expect($guard->toolIsEligibleForProject($missingBackedTool, $project))->toBeFalse();

    $runtimeToolCheck = new ReflectionMethod($guard, 'runtimeToolRelationshipsAreValid');

    expect($runtimeToolCheck->invoke($guard, $missingBackedTool, $project))->toBeFalse();

    $foreignConnector = McpConnector::factory()->for($foreignTeam)->create();

    expect(fn () => $guard->assertToolContractRelationships($team, [
        'application_id' => $application->id,
        'scope' => ToolScope::Project->value,
        'mcp_connector_id' => $foreignConnector->id,
    ]))->toThrow(ValidationException::class);

    $connector = McpConnector::factory()->for($team)->create([
        'application_id' => $application->id,
    ]);
    $dataSource = DataSource::factory()->for($team)->create([
        'application_id' => $application->id,
    ]);

    $guard->assertToolContractRelationships($team, [
        'application_id' => $application->id,
        'scope' => ToolScope::Project->value,
        'mcp_connector_id' => $connector->id,
        'data_source_id' => $dataSource->id,
    ]);

    expect(true)->toBeTrue();
});

test('every server-side tool mode fails closed when approval is revoked before execution', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $runner = app(AgentRunner::class);

    foreach ([
        'executeRemoteHttp' => ExecMode::Http,
        'executeConnector' => ExecMode::Connector,
        'executeKnowledge' => ExecMode::Knowledge,
        'executeDb' => ExecMode::Db,
    ] as $methodName => $mode) {
        $run = AgentRun::factory()->create([
            'agent_id' => $agent->id,
            'project_id' => $agent->project_id,
            'application_id' => $agent->project->application_id,
            'llm_provider_id' => $agent->llm_provider_id,
            'status' => RunStatus::Running,
            'environment' => Environment::Production,
            'started_at' => now(),
            'completed_at' => null,
            'expires_at' => now()->addMinutes(5),
        ]);
        $tool = ToolContract::factory()->for($team)->for($agent->project->application)->create([
            'execution_mode' => $mode,
            'requires_approval' => true,
            'status' => 'Draft',
        ]);
        $call = ToolCall::factory()->create([
            'agent_run_id' => $run->id,
            'tool_contract_id' => $tool->id,
            'tool_name' => $tool->slug,
            'status' => ToolCallStatus::Pending,
            'execution_mode' => $mode,
            'result' => null,
            'completed_at' => null,
        ]);
        $method = new ReflectionMethod($runner, $methodName);

        $failed = $method->invoke($runner, $run, $tool, $call, []);

        expect($failed)->toBeInstanceOf(AgentRun::class)
            ->and($failed->failure_reason)->toBe('tool_requires_approval')
            ->and($call->fresh()->status)->toBe(ToolCallStatus::Failed);
    }
});

test('knowledge and database executor failures become controlled run failures', function () {
    [, $team] = ownerAndTeam();
    $agent = maaccAgent($team);
    $application = $agent->project->application;
    $runner = app(AgentRunner::class);
    $knowledgeSource = KnowledgeSource::factory()->for($team)->disabled()->create([
        'application_id' => $application->id,
    ]);
    $dataSource = DataSource::factory()->for($team)->disabled()->create([
        'application_id' => $application->id,
    ]);

    foreach ([
        [
            'method' => 'executeKnowledge',
            'mode' => ExecMode::Knowledge,
            'attributes' => ['knowledge_source_id' => $knowledgeSource->id],
            'failure' => 'knowledge_unavailable',
        ],
        [
            'method' => 'executeDb',
            'mode' => ExecMode::Db,
            'attributes' => [
                'data_source_id' => $dataSource->id,
                'db_config' => ['query' => 'select * from reporting_metrics'],
            ],
            'failure' => 'db_source_unavailable',
        ],
    ] as $case) {
        $run = AgentRun::factory()->create([
            'agent_id' => $agent->id,
            'project_id' => $agent->project_id,
            'application_id' => $application->id,
            'llm_provider_id' => $agent->llm_provider_id,
            'status' => RunStatus::Running,
            'environment' => Environment::Production,
            'started_at' => now(),
            'completed_at' => null,
            'expires_at' => now()->addMinutes(5),
        ]);
        $tool = ToolContract::factory()->for($team)->for($application)->create([
            'execution_mode' => $case['mode'],
            'requires_approval' => false,
            'status' => 'Active',
            ...$case['attributes'],
        ]);
        $call = ToolCall::factory()->create([
            'agent_run_id' => $run->id,
            'tool_contract_id' => $tool->id,
            'tool_name' => $tool->slug,
            'status' => ToolCallStatus::Pending,
            'execution_mode' => $case['mode'],
            'result' => null,
            'completed_at' => null,
        ]);
        $method = new ReflectionMethod($runner, $case['method']);

        $failed = $method->invoke($runner, $run, $tool, $call, ['query' => 'status']);

        expect($failed)->toBeInstanceOf(AgentRun::class)
            ->and($failed->failure_reason)->toBe($case['failure'])
            ->and($call->fresh()->status)->toBe(ToolCallStatus::Failed);
    }
});
