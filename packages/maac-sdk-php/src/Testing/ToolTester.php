<?php

declare(strict_types=1);

namespace Maac\Sdk\Testing;

use Maac\Sdk\Resources\ManifestTool;
use Maac\Sdk\Resources\Run;
use Maac\Sdk\Resources\ToolCall;
use Maac\Sdk\Tools\ToolContext;
use Maac\Sdk\Tools\ToolHandler;

/**
 * A pre-flight harness for application teams: validate a local client-side tool
 * handler against its MAAC contract — both the arguments it will receive and the
 * result it returns — *before* reporting it as implemented. Catches contract
 * drift in CI instead of at runtime via an `invalid_tool_result`.
 */
final class ToolTester
{
    /**
     * Validate sample arguments against the tool's input schema.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function validateInput(ManifestTool $tool, array $arguments): ValidationResult
    {
        return SchemaValidator::validate($tool->inputSchema, $arguments);
    }

    /**
     * Validate a result against the tool's output schema.
     *
     * @param  array<string, mixed>  $result
     */
    public function validateOutput(ManifestTool $tool, array $result): ValidationResult
    {
        return SchemaValidator::validate($tool->outputSchema, $result);
    }

    /**
     * Run a handler against sample arguments and validate both the arguments
     * (against the input schema) and the returned result (against the output
     * schema). Errors are prefixed `input:`/`output:` so it is obvious which
     * side of the contract failed.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function test(ManifestTool $tool, ToolHandler $handler, array $arguments): ValidationResult
    {
        $errors = array_map(
            static fn (string $error): string => 'input: '.$error,
            $this->validateInput($tool, $arguments)->errors,
        );

        $result = $handler->handle($arguments, $this->synthesizeContext($tool, $arguments));

        $errors = [...$errors, ...array_map(
            static fn (string $error): string => 'output: '.$error,
            $this->validateOutput($tool, $result)->errors,
        )];

        return ValidationResult::fromErrors($errors);
    }

    /**
     * Build a synthetic {@see ToolContext} mirroring a real client-side pause, so
     * a handler can be exercised without a live run.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function synthesizeContext(ManifestTool $tool, array $arguments): ToolContext
    {
        $toolCall = new ToolCall('test-tool-call', $tool->name, $arguments, $tool->outputSchema);
        $run = new Run('test-run', '', Run::STATUS_WAITING, 0, 0, 0.0, null, $toolCall, null);

        return new ToolContext($run, $toolCall);
    }
}
