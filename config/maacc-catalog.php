<?php

/*
|--------------------------------------------------------------------------
| LLM Provider & Model Catalog
|--------------------------------------------------------------------------
|
| A curated, version-controlled catalog of the text-generation providers the
| `laravel/ai` SDK supports (the `Laravel\Ai\Enums\Lab` text row) and their
| well-known model codes. It powers the console's provider picker and model
| autocomplete so operators select a known-good entry instead of hand-typing a
| model code — the mistake that lets a value like `openai/gpt-5.4` reach a
| provider that expects the bare `gpt-5.4`.
|
| `driver` MUST equal the `laravel/ai` provider key. `label` is what the console
| shows and what is stored on the catalog entry (it must contain the driver
| keyword so LlmProvider::driverFor() resolves it). Each model `code` is the
| BARE id the provider's API expects — never prefixed with the provider.
|
| These figures are a reviewed STARTING POINT: context windows and per-1M-token
| prices drift, so treat them as editable defaults. Every field remains editable
| in the console, and a live verification is still required before publish, so a
| stale entry here can never silently reach production. A scheduled sync against
| each provider's models API refreshes this list (see the catalog sync command).
|
*/

return [

    'providers' => [

        [
            'driver' => 'openai',
            'label' => 'OpenAI',
            'models' => [
                ['code' => 'gpt-5.4', 'label' => 'GPT-5.4', 'context' => '400K', 'input' => 1.25, 'output' => 10.0],
                ['code' => 'gpt-4o', 'label' => 'GPT-4o', 'context' => '128K', 'input' => 2.5, 'output' => 10.0],
                ['code' => 'gpt-4o-mini', 'label' => 'GPT-4o mini', 'context' => '128K', 'input' => 0.15, 'output' => 0.6],
                ['code' => 'o3', 'label' => 'o3', 'context' => '200K', 'input' => 2.0, 'output' => 8.0],
            ],
        ],

        [
            'driver' => 'anthropic',
            'label' => 'Anthropic',
            'models' => [
                ['code' => 'claude-opus-4-8', 'label' => 'Claude Opus 4.8', 'context' => '200K', 'input' => 5.0, 'output' => 25.0],
                ['code' => 'claude-sonnet-5', 'label' => 'Claude Sonnet 5', 'context' => '200K', 'input' => 3.0, 'output' => 15.0],
                ['code' => 'claude-haiku-4-5-20251001', 'label' => 'Claude Haiku 4.5', 'context' => '200K', 'input' => 1.0, 'output' => 5.0],
            ],
        ],

        [
            'driver' => 'gemini',
            'label' => 'Google Gemini',
            'models' => [
                ['code' => 'gemini-2.5-pro', 'label' => 'Gemini 2.5 Pro', 'context' => '1M', 'input' => 1.25, 'output' => 10.0],
                ['code' => 'gemini-2.5-flash', 'label' => 'Gemini 2.5 Flash', 'context' => '1M', 'input' => 0.3, 'output' => 2.5],
            ],
        ],

        [
            'driver' => 'azure',
            'label' => 'Azure OpenAI',
            'models' => [
                ['code' => 'gpt-4o', 'label' => 'GPT-4o (Azure)', 'context' => '128K', 'input' => 2.5, 'output' => 10.0],
                ['code' => 'gpt-4o-mini', 'label' => 'GPT-4o mini (Azure)', 'context' => '128K', 'input' => 0.15, 'output' => 0.6],
            ],
        ],

        [
            'driver' => 'bedrock',
            'label' => 'AWS Bedrock',
            'models' => [
                ['code' => 'anthropic.claude-sonnet-5-v1:0', 'label' => 'Claude Sonnet 5 (Bedrock)', 'context' => '200K', 'input' => 3.0, 'output' => 15.0],
                ['code' => 'meta.llama3-3-70b-instruct-v1:0', 'label' => 'Llama 3.3 70B (Bedrock)', 'context' => '128K', 'input' => 0.72, 'output' => 0.72],
            ],
        ],

        [
            'driver' => 'groq',
            'label' => 'Groq',
            'models' => [
                ['code' => 'llama-3.3-70b-versatile', 'label' => 'Llama 3.3 70B', 'context' => '128K', 'input' => 0.59, 'output' => 0.79],
            ],
        ],

        [
            'driver' => 'xai',
            'label' => 'xAI',
            'models' => [
                ['code' => 'grok-4', 'label' => 'Grok 4', 'context' => '256K', 'input' => 3.0, 'output' => 15.0],
                ['code' => 'grok-3-mini', 'label' => 'Grok 3 mini', 'context' => '128K', 'input' => 0.3, 'output' => 0.5],
            ],
        ],

        [
            'driver' => 'deepseek',
            'label' => 'DeepSeek',
            'models' => [
                ['code' => 'deepseek-chat', 'label' => 'DeepSeek Chat', 'context' => '64K', 'input' => 0.27, 'output' => 1.1],
                ['code' => 'deepseek-reasoner', 'label' => 'DeepSeek Reasoner', 'context' => '64K', 'input' => 0.55, 'output' => 2.19],
            ],
        ],

        [
            'driver' => 'mistral',
            'label' => 'Mistral',
            'models' => [
                ['code' => 'mistral-large-latest', 'label' => 'Mistral Large', 'context' => '128K', 'input' => 2.0, 'output' => 6.0],
                ['code' => 'mistral-small-latest', 'label' => 'Mistral Small', 'context' => '128K', 'input' => 0.2, 'output' => 0.6],
            ],
        ],

        [
            'driver' => 'openrouter',
            'label' => 'OpenRouter',
            'models' => [
                // OpenRouter legitimately addresses models by a `vendor/model`
                // code — the one place a slash in the code is correct.
                ['code' => 'openai/gpt-5.4', 'label' => 'GPT-5.4 (via OpenRouter)', 'context' => '400K', 'input' => 1.25, 'output' => 10.0],
                ['code' => 'anthropic/claude-opus-4.8', 'label' => 'Claude Opus 4.8 (via OpenRouter)', 'context' => '200K', 'input' => 5.0, 'output' => 25.0],
            ],
        ],

        [
            'driver' => 'ollama',
            'label' => 'Ollama (self-hosted)',
            'models' => [
                ['code' => 'llama3.3', 'label' => 'Llama 3.3', 'context' => '128K', 'input' => 0.0, 'output' => 0.0],
                ['code' => 'qwen2.5', 'label' => 'Qwen 2.5', 'context' => '128K', 'input' => 0.0, 'output' => 0.0],
            ],
        ],

    ],

];
