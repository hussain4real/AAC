<?php

use App\Enums\Environment;
use App\Enums\ExecMode;
use App\Enums\ImplStatus;
use App\Enums\SdkLanguage;
use App\Models\Application;
use App\Models\ToolContract;
use App\Models\ToolImplementation;
use App\Support\Sdk\SdkCompatibilityReport;

beforeEach(function () {
    config()->set('maac.sdk.api_version', '1.0.0');
    config()->set('maac.sdk.minimum_client_version', '1.0.0');
    config()->set('maac.sdk.current_client_version', '1.4.0');

    [, $this->team] = ownerAndTeam();
    $this->application = Application::factory()->for($this->team)->create([
        'environment' => Environment::Production,
    ]);
});

function clientTool(string $version = '2.0.0'): ToolContract
{
    return ToolContract::factory()->for(test()->team)->for(test()->application)->create([
        'execution_mode' => ExecMode::Client,
        'version' => $version,
    ]);
}

test('the report carries the versioned platform descriptor', function () {
    $report = app(SdkCompatibilityReport::class)->forTeam($this->team);

    expect($report['platform']['api_version'])->toBe('1.0.0')
        ->and($report['platform']['minimum_client_version'])->toBe('1.0.0')
        ->and($report['platform'])->toHaveKeys(['packages', 'deprecations', 'languages']);
});

test('it summarises an implemented, compatible application', function () {
    $tool = clientTool('2.0.0');
    ToolImplementation::factory()->create([
        'tool_contract_id' => $tool->id,
        'application_id' => $this->application->id,
        'environment' => Environment::Production,
        'status' => ImplStatus::Implemented,
        'implemented_version' => '2.0.0',
        'language' => SdkLanguage::Php,
        'sdk_version' => '1.0.0',
    ]);

    $health = app(SdkCompatibilityReport::class)->forTeam($this->team)['applications'][0];

    expect($health['id'])->toBe($this->application->slug)
        ->and($health['tools']['implemented'])->toBe(1)
        ->and($health['tools']['total'])->toBe(1)
        ->and($health['compatible'])->toBeTrue()
        ->and($health['clients'])->toHaveCount(1)
        ->and($health['clients'][0]['version'])->toBe('1.0.0')
        ->and($health['clients'][0]['status'])->toBe('compatible');
});

test('it flags an application whose SDK client is below the supported minimum', function () {
    $tool = clientTool('2.0.0');
    ToolImplementation::factory()->create([
        'tool_contract_id' => $tool->id,
        'application_id' => $this->application->id,
        'environment' => Environment::Production,
        'status' => ImplStatus::Implemented,
        'implemented_version' => '2.0.0',
        'language' => SdkLanguage::Php,
        'sdk_version' => '0.9.0',
    ]);

    $health = app(SdkCompatibilityReport::class)->forTeam($this->team)['applications'][0];

    expect($health['compatible'])->toBeFalse()
        ->and($health['clients'][0]['status'])->toBe('upgrade_required');
});

test('it lists drifted tool implementations needing migration', function () {
    $outdated = clientTool('2.0.0');
    ToolImplementation::factory()->create([
        'tool_contract_id' => $outdated->id,
        'application_id' => $this->application->id,
        'environment' => Environment::Production,
        'status' => ImplStatus::Outdated,
        'implemented_version' => '1.0.0',
        'sdk_version' => '1.0.0',
    ]);

    $incompatible = clientTool('3.0.0');
    ToolImplementation::factory()->create([
        'tool_contract_id' => $incompatible->id,
        'application_id' => $this->application->id,
        'environment' => Environment::Production,
        'status' => ImplStatus::Incompatible,
        'implemented_version' => '3.0.0',
        'sdk_version' => '1.0.0',
    ]);

    $report = app(SdkCompatibilityReport::class)->forTeam($this->team);

    expect($report['drift'])->toHaveCount(2)
        ->and(collect($report['drift'])->pluck('status')->sort()->values()->all())
        ->toBe(['incompatible', 'outdated'])
        ->and($report['applications'][0]['tools']['outdated'])->toBe(1)
        ->and($report['applications'][0]['tools']['incompatible'])->toBe(1);
});

test('an application with no client tools is vacuously compatible with no drift', function () {
    $report = app(SdkCompatibilityReport::class)->forTeam($this->team);

    expect($report['applications'][0]['tools']['total'])->toBe(0)
        ->and($report['applications'][0]['compatible'])->toBeTrue()
        ->and($report['applications'][0]['clients'])->toBe([])
        ->and($report['drift'])->toBe([]);
});

test('an implementation in another environment is treated as not implemented', function () {
    $tool = clientTool('2.0.0');
    ToolImplementation::factory()->create([
        'tool_contract_id' => $tool->id,
        'application_id' => $this->application->id,
        'environment' => Environment::Staging,
        'status' => ImplStatus::Implemented,
        'implemented_version' => '2.0.0',
        'sdk_version' => '1.0.0',
    ]);

    $health = app(SdkCompatibilityReport::class)->forTeam($this->team)['applications'][0];

    expect($health['tools']['required'])->toBe(1)
        ->and($health['tools']['implemented'])->toBe(0)
        ->and($health['clients'])->toBe([]);
});
