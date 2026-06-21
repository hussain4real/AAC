<?php

use App\Enums\AlertSeverity;
use App\Enums\ApprovalStatus;
use App\Enums\ApprovalType;
use App\Enums\QuotaScope;
use App\Enums\Sensitivity;

test('approval type exposes a queue bucket, label, and options', function () {
    expect(ApprovalType::ToolContract->queue())->toBe('tools')
        ->and(ApprovalType::AgentPublication->queue())->toBe('agents')
        ->and(ApprovalType::ModelAccess->queue())->toBe('models')
        ->and(ApprovalType::CredentialChange->queue())->toBe('data')
        ->and(ApprovalType::AgentPublication->label())->toBe('Agent Publication')
        ->and(ApprovalType::options())->toHaveCount(4)
        ->and(ApprovalType::options()[0])->toHaveKeys(['value', 'label']);
});

test('approval status reports pending and decided states', function () {
    expect(ApprovalStatus::Pending->isPending())->toBeTrue()
        ->and(ApprovalStatus::Pending->isDecided())->toBeFalse()
        ->and(ApprovalStatus::Approved->isPending())->toBeFalse()
        ->and(ApprovalStatus::Approved->isDecided())->toBeTrue()
        ->and(ApprovalStatus::Rejected->label())->toBe('Rejected');
});

test('quota scope reports whether it targets a subject', function () {
    expect(QuotaScope::Platform->requiresSubject())->toBeFalse()
        ->and(QuotaScope::Application->requiresSubject())->toBeTrue()
        ->and(QuotaScope::Agent->requiresSubject())->toBeTrue()
        ->and(QuotaScope::Model->label())->toBe('Model')
        ->and(QuotaScope::options())->toHaveCount(5);
});

test('alert severity exposes a label and sort weight', function () {
    expect(AlertSeverity::High->label())->toBe('High')
        ->and(AlertSeverity::Medium->label())->toBe('Medium')
        ->and(AlertSeverity::Low->label())->toBe('Low')
        ->and(AlertSeverity::High->weight())->toBeGreaterThan(AlertSeverity::Medium->weight())
        ->and(AlertSeverity::Medium->weight())->toBeGreaterThan(AlertSeverity::Low->weight())
        ->and(AlertSeverity::Medium->value)->toBe('med');
});

test('sensitivity levels rank and flag masking', function () {
    expect(Sensitivity::Public->level())->toBe(0)
        ->and(Sensitivity::Restricted->level())->toBe(3)
        ->and(Sensitivity::Restricted->isAtLeast(Sensitivity::Confidential))->toBeTrue()
        ->and(Sensitivity::Internal->isAtLeast(Sensitivity::Confidential))->toBeFalse()
        ->and(Sensitivity::Public->requiresMasking())->toBeFalse()
        ->and(Sensitivity::Internal->requiresMasking())->toBeFalse()
        ->and(Sensitivity::Confidential->requiresMasking())->toBeTrue()
        ->and(Sensitivity::Restricted->requiresMasking())->toBeTrue();
});
