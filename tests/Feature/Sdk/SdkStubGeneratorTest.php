<?php

use App\Enums\SdkLanguage;
use App\Models\ToolContract;
use App\Support\Sdk\SdkStubGenerator;

beforeEach(function () {
    $this->generator = new SdkStubGenerator;
    $this->tool = ToolContract::factory()->make([
        'slug' => 'getOperationalRecords',
        'version' => '2.1.0',
        'input_schema' => ['from_date' => 'string·date', 'limit' => 'number?'],
        'output_schema' => ['summary' => 'object', 'records' => 'array'],
    ]);
});

test('it generates a stub for every supported language', function () {
    $stubs = $this->generator->forContract($this->tool);

    expect($stubs)->toHaveKeys(['typescript', 'php', 'python']);
});

test('the typescript stub registers a real handler with args, output and permission hint', function () {
    $stub = $this->generator->generate($this->tool, SdkLanguage::TypeScript);

    expect($stub)
        ->toContain('import { ToolHandlerRegistry } from "@qatar-navigation-milaha/sdk"')
        ->toContain('registry.register("getOperationalRecords"')
        ->toContain('getoperationalrecords:read')   // permission hint (app-owned authorization)
        ->toContain('from_date: args.from_date')    // argument shape
        ->toContain('records: result.records')       // output shape / return pattern
        ->toContain('contract v2.1.0')
        ->not->toContain('ctx.user');                // the fictional API is gone
});

test('the php stub registers a real handler with args, output and permission hint', function () {
    $stub = $this->generator->generate($this->tool, SdkLanguage::Php);

    expect($stub)
        ->toContain('use Maac\\Sdk\\Tools\\ToolHandlerRegistry;')
        ->toContain("registerCallable('getOperationalRecords'")
        ->toContain('getoperationalrecords:read')
        ->toContain("'from_date' => \$args['from_date'] ?? null")
        ->toContain("'records' => \$result['records']")
        ->not->toContain('->user->can');
});

test('the python stub registers a real handler with args, output and permission hint', function () {
    $stub = $this->generator->generate($this->tool, SdkLanguage::Python);

    expect($stub)
        ->toContain('from maac_sdk import ToolHandlerRegistry')
        ->toContain('@registry.register("getOperationalRecords")')
        ->toContain('def getOperationalRecords(')
        ->toContain('getoperationalrecords:read')
        ->toContain('from_date=args.get("from_date")')
        ->toContain('"records": result["records"]')
        ->not->toContain('has_permission');
});

test('boolean and integer schema types map to language-native types', function () {
    $tool = ToolContract::factory()->make([
        'slug' => 'checkFlags',
        'input_schema' => ['active' => 'boolean', 'count' => 'integer'],
        'output_schema' => ['ok' => 'boolean'],
    ]);

    expect($this->generator->generate($tool, SdkLanguage::TypeScript))
        ->toContain('active: boolean')
        ->toContain('count: number');

    expect($this->generator->generate($tool, SdkLanguage::Php))
        ->toContain('active: bool')
        ->toContain('count: int');

    expect($this->generator->generate($tool, SdkLanguage::Python))
        ->toContain('active: bool')
        ->toContain('count: int');
});

test('python function names are sanitized into valid identifiers', function () {
    $hyphenated = ToolContract::factory()->make([
        'slug' => '9-get-records',
        'input_schema' => ['q' => 'string'],
        'output_schema' => ['r' => 'array'],
    ]);

    $stub = $this->generator->generate($hyphenated, SdkLanguage::Python);

    expect($stub)->toContain('def tool_9_get_records(');
});
