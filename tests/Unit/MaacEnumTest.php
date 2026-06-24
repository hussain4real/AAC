<?php

use App\Enums\AgentStatus;
use App\Enums\AppStatus;
use App\Enums\CredentialStatus;
use App\Enums\Environment;
use App\Enums\EvaluationCaseKind;
use App\Enums\EvaluationStatus;
use App\Enums\ExecMode;
use App\Enums\HttpMethod;
use App\Enums\ImplStatus;
use App\Enums\KnowledgeSourceStatus;
use App\Enums\LlmStatus;
use App\Enums\MaacPermission;
use App\Enums\MaacRole;
use App\Enums\McpConnectorStatus;
use App\Enums\ProjectStatus;
use App\Enums\RemoteAuthType;
use App\Enums\RunStatus;
use App\Enums\Sensitivity;
use App\Enums\ToolCallStatus;
use App\Enums\ToolScope;
use App\Enums\TraceEventType;

test('every MAAC enum case has a non-empty label', function (string $enum) {
    foreach ($enum::cases() as $case) {
        expect($case->label())->toBeString()->not->toBeEmpty();
    }
})->with([
    Environment::class,
    Sensitivity::class,
    ExecMode::class,
    ToolScope::class,
    ImplStatus::class,
    AgentStatus::class,
    AppStatus::class,
    ProjectStatus::class,
    RunStatus::class,
    LlmStatus::class,
    CredentialStatus::class,
    ToolCallStatus::class,
    TraceEventType::class,
    MaacRole::class,
    HttpMethod::class,
    RemoteAuthType::class,
    McpConnectorStatus::class,
]);

test('enums expose value/label option pairs', function (string $enum) {
    $options = $enum::options();

    expect($options)->toHaveCount(count($enum::cases()))
        ->and($options[0])->toHaveKeys(['value', 'label']);
})->with([
    Environment::class,
    Sensitivity::class,
    ExecMode::class,
    ToolScope::class,
    ImplStatus::class,
    AgentStatus::class,
    AppStatus::class,
    ProjectStatus::class,
    RunStatus::class,
    LlmStatus::class,
    MaacRole::class,
    HttpMethod::class,
    RemoteAuthType::class,
    McpConnectorStatus::class,
    KnowledgeSourceStatus::class,
    EvaluationStatus::class,
    EvaluationCaseKind::class,
]);

test('enum helper predicates behave as expected', function () {
    expect(ExecMode::Client->isClientSide())->toBeTrue()
        ->and(ExecMode::Hosted->isClientSide())->toBeFalse()
        ->and(ExecMode::Knowledge->isClientSide())->toBeFalse()
        ->and(AgentStatus::Published->isPublished())->toBeTrue()
        ->and(AgentStatus::Draft->isPublished())->toBeFalse()
        ->and(CredentialStatus::Active->isUsable())->toBeTrue()
        ->and(CredentialStatus::Revoked->isUsable())->toBeFalse()
        ->and(RunStatus::Completed->isTerminal())->toBeTrue()
        ->and(RunStatus::Running->isTerminal())->toBeFalse()
        ->and(KnowledgeSourceStatus::Active->isActive())->toBeTrue()
        ->and(KnowledgeSourceStatus::Draft->isActive())->toBeFalse()
        ->and(EvaluationStatus::Passed->isComplete())->toBeTrue()
        ->and(EvaluationStatus::Passed->hasPassed())->toBeTrue()
        ->and(EvaluationStatus::Failed->isComplete())->toBeTrue()
        ->and(EvaluationStatus::Failed->hasPassed())->toBeFalse()
        ->and(EvaluationStatus::Running->isComplete())->toBeFalse()
        ->and(EvaluationCaseKind::ClientTool->usesClientTool())->toBeTrue()
        ->and(EvaluationCaseKind::Rag->usesClientTool())->toBeFalse();
});

test('every MAAC role grants a permission set including a viewer baseline', function () {
    foreach (MaacRole::cases() as $role) {
        expect($role->permissions())->toBeArray()->not->toBeEmpty();
    }

    expect(MaacRole::Viewer->hasPermission(MaacPermission::View))->toBeTrue()
        ->and(MaacRole::Viewer->hasPermission(MaacPermission::ManagePlatform))->toBeFalse()
        ->and(MaacRole::PlatformAdmin->hasPermission(MaacPermission::ManageCredential))->toBeTrue();
});
