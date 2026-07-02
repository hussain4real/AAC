<?php

namespace App\Support\Sdk;

use App\Enums\SdkLanguage;
use App\Models\ToolContract;
use Illuminate\Support\Str;

/**
 * Generates client-side tool handler stubs from a tool contract.
 *
 * Every generated stub includes the tool name, the argument shape (from the
 * input schema), the output shape (from the output schema), a caller-permission
 * placeholder, and the result return pattern — the five things an application
 * developer needs to implement a compatible handler.
 */
class SdkStubGenerator
{
    /**
     * Generate a stub for the given contract in every supported language.
     *
     * @return array<string, string> keyed by {@see SdkLanguage} value
     */
    public function forContract(ToolContract $tool): array
    {
        $stubs = [];

        foreach (SdkLanguage::cases() as $language) {
            $stubs[$language->value] = $this->generate($tool, $language);
        }

        return $stubs;
    }

    /**
     * Generate a handler stub for the given contract and language.
     */
    public function generate(ToolContract $tool, SdkLanguage $language): string
    {
        return match ($language) {
            SdkLanguage::TypeScript => $this->typescript($tool),
            SdkLanguage::Php => $this->php($tool),
            SdkLanguage::Python => $this->python($tool),
        };
    }

    /**
     * The permission slug a handler should enforce before returning data.
     */
    public function permission(ToolContract $tool): string
    {
        return Str::lower($tool->slug).':read';
    }

    private function typescript(ToolContract $tool): string
    {
        $perm = $this->permission($tool);
        $argShape = $this->shape($tool->input_schema, fn (string $t): string => $this->tsType($t), ': ', '; ');
        $outShape = $this->shape($tool->output_schema, fn (string $t): string => $this->tsType($t), ': ', '; ');
        $queryArgs = $this->lines($tool->input_schema, fn (string $f): string => "    {$f}: args.{$f},");
        $returnArgs = $this->lines($tool->output_schema, fn (string $f): string => "    {$f}: result.{$f},");

        return <<<TS
        import { ToolHandlerRegistry } from "@qatar-navigation-milaha/sdk";

        // Handler for the "{$tool->slug}" client-side tool (contract v{$tool->version}).
        // Pass `registry` to client.run(agentSlug, input, registry) — MAAC pauses the run here.
        const registry = new ToolHandlerRegistry();

        registry.register("{$tool->slug}", (args, ctx) => {
          // ctx: { run, toolCall }. Enforce YOUR app's own authorization (e.g. "{$perm}").
          // args: { {$argShape} }
          const result = yourApp.query({
        {$queryArgs}
          });

          // Result must satisfy the output schema: { {$outShape} }
          return {
        {$returnArgs}
          };
        });
        TS;
    }

    private function php(ToolContract $tool): string
    {
        $perm = $this->permission($tool);
        $argShape = $this->shape($tool->input_schema, fn (string $t): string => $this->phpType($t), ': ', ', ');
        $outShape = $this->shape($tool->output_schema, fn (string $t): string => $this->phpType($t), ': ', ', ');
        $queryArgs = $this->lines($tool->input_schema, fn (string $f): string => "        '{$f}' => \$args['{$f}'] ?? null,");
        $returnArgs = $this->lines($tool->output_schema, fn (string $f): string => "        '{$f}' => \$result['{$f}'],");

        return <<<PHP
        <?php

        use Maac\\Sdk\\Tools\\ToolContext;
        use Maac\\Sdk\\Tools\\ToolHandlerRegistry;

        // Handler for the "{$tool->slug}" client-side tool (contract v{$tool->version}).
        // Pass \$registry to \$client->run(\$agentSlug, \$input, \$registry) — MAAC pauses the run here.
        \$registry = new ToolHandlerRegistry;

        \$registry->registerCallable('{$tool->slug}', function (array \$args, ToolContext \$ctx): array {
            // \$ctx: run + toolCall. Enforce YOUR app's own authorization (e.g. '{$perm}').
            // \$args: array{{$argShape}}
            \$result = YourApp::query([
        {$queryArgs}
            ]);

            // Result must satisfy the output schema: array{{$outShape}}
            return [
        {$returnArgs}
            ];
        });
        PHP;
    }

    private function python(ToolContract $tool): string
    {
        $perm = $this->permission($tool);
        $fn = $this->pythonIdentifier($tool->slug);
        $argShape = $this->shape($tool->input_schema, fn (string $t): string => $this->pythonType($t), ': ', ', ');
        $outShape = $this->shape($tool->output_schema, fn (string $t): string => $this->pythonType($t), ': ', ', ');
        $queryArgs = $this->lines($tool->input_schema, fn (string $f): string => "        {$f}=args.get(\"{$f}\"),");
        $returnArgs = $this->lines($tool->output_schema, fn (string $f): string => "        \"{$f}\": result[\"{$f}\"],");

        return <<<PY
        from maac_sdk import ToolHandlerRegistry

        registry = ToolHandlerRegistry()


        # Handler for the "{$tool->slug}" client-side tool (contract v{$tool->version}).
        @registry.register("{$tool->slug}")
        def {$fn}(args: dict, ctx: dict) -> dict:
            # ctx: run + tool_call. Enforce YOUR app's own authorization (e.g. "{$perm}").
            # args: {{$argShape}}
            result = your_app.query(
        {$queryArgs}
            )

            # Result must satisfy the output schema: {{$outShape}}
            return {
        {$returnArgs}
            }
        PY;
    }

    /**
     * Render a one-line "field: type" shape comment from a schema map.
     *
     * @param  array<string, string>  $schema
     * @param  callable(string): string  $typeMap
     */
    private function shape(array $schema, callable $typeMap, string $sep, string $glue): string
    {
        $parts = [];

        foreach ($schema as $field => $definition) {
            $optional = ToolSchema::isOptional($definition) ? '?' : '';
            $parts[] = $field.$optional.$sep.$typeMap(ToolSchema::baseType($definition));
        }

        return implode($glue, $parts);
    }

    /**
     * Render newline-joined body lines from a schema map.
     *
     * @param  array<string, string>  $schema
     * @param  callable(string): string  $lineMap
     */
    private function lines(array $schema, callable $lineMap): string
    {
        return implode("\n", array_map($lineMap, array_keys($schema)));
    }

    private function tsType(string $base): string
    {
        return match ($base) {
            'string' => 'string',
            'number', 'integer' => 'number',
            'boolean' => 'boolean',
            'array' => 'unknown[]',
            default => 'Record<string, unknown>',
        };
    }

    private function phpType(string $base): string
    {
        return match ($base) {
            'string' => 'string',
            'number' => 'float',
            'integer' => 'int',
            'boolean' => 'bool',
            default => 'array',
        };
    }

    private function pythonType(string $base): string
    {
        return match ($base) {
            'string' => 'str',
            'number' => 'float',
            'integer' => 'int',
            'boolean' => 'bool',
            'array' => 'list',
            default => 'dict',
        };
    }

    /**
     * Coerce a tool slug into a safe Python function identifier.
     */
    private function pythonIdentifier(string $slug): string
    {
        $identifier = (string) preg_replace('/[^A-Za-z0-9_]/', '_', $slug);

        return ctype_digit($identifier[0] ?? '0') ? 'tool_'.$identifier : $identifier;
    }
}
