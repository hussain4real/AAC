<?php

namespace App\Support\Runtime\HostedTools;

use App\Support\Runtime\Contracts\HostedTool;

/**
 * Built-in hosted tool that returns live operational status for a vessel from
 * MAACC's fleet system. The model has no way to know this data on its own, so it
 * must call the tool — the canonical reason a tool exists.
 *
 * Contract shape: input `{ "vessel": "string" }`,
 * output `{ "vessel": "string", "status": "string", "port": "string", "eta": "string" }`.
 */
class VesselStatusHostedTool implements HostedTool
{
    /**
     * Deterministic fleet snapshot, keyed by a normalized vessel name fragment.
     *
     * @var array<string, array{status: string, port: string, eta: string}>
     */
    private const FLEET = [
        'al-zubarah' => ['status' => 'Delayed — berth congestion', 'port' => 'Hamad', 'eta' => '2026-06-26 14:20 AST'],
        'doha pearl' => ['status' => 'On time', 'port' => 'Doha', 'eta' => '2026-06-25 22:05 AST'],
        'umm salal' => ['status' => 'Berthed', 'port' => 'Hamad', 'eta' => 'Arrived'],
    ];

    /**
     * Look up the current status for the requested vessel.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments): array
    {
        $vessel = is_string($arguments['vessel'] ?? null) ? trim($arguments['vessel']) : '';
        $needle = strtolower($vessel);

        foreach (self::FLEET as $key => $record) {
            if ($needle !== '' && str_contains($needle, $key)) {
                return ['vessel' => $vessel, ...$record];
            }
        }

        return [
            'vessel' => $vessel,
            'status' => 'No active voyage on record',
            'port' => 'Unknown',
            'eta' => 'Unknown',
        ];
    }
}
