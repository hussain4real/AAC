<?php

namespace App\Support\Runtime\HostedTools;

use App\Enums\ExecMode;
use App\Models\ToolContract;
use App\Support\Runtime\LlmProviderToolDefinition;

/**
 * Resolves hosted MAACC contracts that are actually executed by the model
 * provider, not by a PHP handler inside MAACC.
 */
class ProviderHostedToolRegistry
{
    /**
     * @var array<int, string>
     */
    private const WEB_SEARCH_SLUGS = ['webSearch', 'web_search'];

    /**
     * Resolve the provider-side tool definition for a MAACC-hosted contract.
     */
    public function definitionFor(ToolContract $tool): ?LlmProviderToolDefinition
    {
        if ($tool->execution_mode !== ExecMode::Hosted) {
            return null;
        }

        if (in_array($tool->slug, self::WEB_SEARCH_SLUGS, true)) {
            return LlmProviderToolDefinition::webSearch($tool->slug);
        }

        return null;
    }

    /**
     * Determine whether the hosted contract is provider-executed.
     */
    public function has(ToolContract $tool): bool
    {
        return $this->definitionFor($tool) instanceof LlmProviderToolDefinition;
    }
}
