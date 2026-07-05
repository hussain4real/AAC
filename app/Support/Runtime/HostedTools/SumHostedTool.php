<?php

namespace App\Support\Runtime\HostedTools;

use App\Support\Runtime\Contracts\HostedTool;

/**
 * Built-in hosted tool that adds a list of numbers and returns the exact total.
 * A model offloads arithmetic to this MAACC-hosted handler instead of computing
 * it itself, which is exactly the kind of work a tool call exists for.
 *
 * Contract shape: input `{ "numbers": "array" }`, output `{ "total": "number" }`.
 */
class SumHostedTool implements HostedTool
{
    /**
     * Sum the numeric values supplied by the model.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments): array
    {
        $numbers = $arguments['numbers'] ?? [];
        $total = 0.0;

        if (is_array($numbers)) {
            foreach ($numbers as $number) {
                if (is_numeric($number)) {
                    $total += (float) $number;
                }
            }
        }

        return [
            'total' => $total,
        ];
    }
}
