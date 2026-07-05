<?php

namespace App\Support\Runtime;

/**
 * A provider-hosted tool exposed to the model through the underlying AI
 * provider instead of MAACC's local tool execution loop.
 */
final readonly class LlmProviderToolDefinition
{
    public const WEB_SEARCH = 'web_search';

    public function __construct(
        public string $name,
        public string $type,
    ) {}

    /**
     * Build the Laravel AI web-search provider tool definition.
     */
    public static function webSearch(string $name = 'webSearch'): self
    {
        return new self($name, self::WEB_SEARCH);
    }
}
