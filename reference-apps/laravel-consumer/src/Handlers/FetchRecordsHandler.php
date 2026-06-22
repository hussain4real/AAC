<?php

declare(strict_types=1);

namespace Maac\Reference\Laravel\Handlers;

use Maac\Reference\Laravel\Support\CargoRepository;
use Maac\Sdk\Tools\ToolContext;
use Maac\Sdk\Tools\ToolHandler;

/**
 * The Laravel app's local implementation of the client-side "fetch records"
 * tool contract. It pulls from the application-owned {@see CargoRepository} and
 * shapes the result to the contract's output schema (`records`, `total`).
 */
final class FetchRecordsHandler implements ToolHandler
{
    public function __construct(
        private readonly CargoRepository $repository,
        private readonly string $tool,
    ) {}

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
        $query = is_string($arguments['query'] ?? null) ? $arguments['query'] : '';
        $records = $this->repository->search($query);

        return [
            'records' => array_values($records),
            'total' => count($records),
        ];
    }
}
