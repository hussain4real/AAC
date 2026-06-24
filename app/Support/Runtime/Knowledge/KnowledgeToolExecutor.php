<?php

namespace App\Support\Runtime\Knowledge;

use App\Enums\Environment;
use App\Models\KnowledgeSource;
use App\Models\ToolContract;
use App\Support\Runtime\Knowledge\Contracts\KnowledgeRetriever;
use App\Support\Runtime\ToolExecutionException;

/**
 * Executes a knowledge-retrieval (RAG) tool: it resolves the tool's governed
 * knowledge source, enforces that the source is active and available in the
 * run's environment, retrieves the most relevant chunks for the query, and
 * returns the matched passages plus their citations (source attribution +
 * freshness). Every failure mode is a controlled {@see ToolExecutionException}
 * so the runtime records a named run failure; the returned array is validated
 * against the tool's output schema by the caller.
 */
class KnowledgeToolExecutor
{
    public function __construct(private readonly KnowledgeRetriever $retriever) {}

    /**
     * Execute the retrieval against the model-supplied arguments.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     *
     * @throws ToolExecutionException
     */
    public function execute(ToolContract $tool, ?Environment $environment, array $arguments): array
    {
        $source = $tool->knowledgeSource;

        if (! $source instanceof KnowledgeSource) {
            throw ToolExecutionException::knowledgeMisconfigured(
                "The tool [{$tool->slug}] is not mapped to a knowledge source.",
            );
        }

        $envValue = $environment?->value;

        if ($envValue === null || ! $source->isAvailableIn($envValue)) {
            throw ToolExecutionException::knowledgeUnavailable(
                "The knowledge source [{$source->slug}] is disabled or not available in the [".($envValue ?? 'unknown').'] environment.',
            );
        }

        $query = $this->resolveQuery($arguments);

        if ($query === '') {
            throw ToolExecutionException::knowledgeFailed('The knowledge tool requires a non-empty query argument.');
        }

        $config = $tool->knowledgeConfig();
        $topK = max(1, (int) ($config['top_k'] ?? config('maac.runtime.knowledge.default_top_k', 5)));
        $minScore = (float) ($config['min_score'] ?? config('maac.runtime.knowledge.default_min_score', 0.1));

        $matches = $this->retriever->retrieve($source, $query, $topK, $minScore);

        return [
            'matches' => array_map(fn (KnowledgeMatch $match): array => $match->toMatch(), $matches),
            'citations' => array_map(fn (KnowledgeMatch $match): array => $match->toCitation(), $matches),
        ];
    }

    /**
     * Resolve the query from the arguments: the `query` field when present,
     * otherwise the first string argument.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function resolveQuery(array $arguments): string
    {
        if (isset($arguments['query']) && is_string($arguments['query'])) {
            return trim($arguments['query']);
        }

        foreach ($arguments as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return '';
    }
}
