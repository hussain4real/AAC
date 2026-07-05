<?php

declare(strict_types=1);

namespace Maacc\Reference\Cli;

use Maacc\Sdk\Tools\ToolContext;
use Maacc\Sdk\Tools\ToolHandler;

/**
 * A client-side tool handler implemented in plain PHP — no framework, no ORM,
 * no container. It proves the MAACC integration contract is reusable from a bare
 * PHP runtime: the only dependency is the framework-agnostic SDK.
 */
final class FetchRecordsHandler implements ToolHandler
{
    private const RECORDS = [
        'Berth A1 — clear',
        'Berth B3 — loading MV Lusail',
        'Crane 7 — scheduled maintenance',
        'Gate 2 — 14 trucks queued',
    ];

    public function __construct(private readonly string $tool) {}

    public function tool(): string
    {
        return $this->tool;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function handle(array $arguments, ToolContext $context): array
    {
        $query = is_string($arguments['query'] ?? null) ? mb_strtolower($arguments['query']) : '';

        $records = array_values(array_filter(
            self::RECORDS,
            static fn (string $record): bool => $query === '' || str_contains(mb_strtolower($record), $query),
        ));

        return [
            'records' => $records,
            'total' => count($records),
        ];
    }
}
