<?php

namespace App\Support\Runtime;

use InvalidArgumentException;
use Laravel\Ai\AiManager;
use Laravel\Ai\Contracts\Providers\TextProvider;

class RequestScopedAiProviderFactory
{
    /**
     * Create an uncached provider instance with credentials scoped to one call.
     */
    public function __construct(private readonly AiManager $manager) {}

    public function make(string $name, ?string $apiKey): TextProvider
    {
        $config = $this->manager->getInstanceConfig($name);

        if ($apiKey !== null) {
            $config['key'] = $apiKey;
        }

        return match ($config['driver']) {
            'anthropic' => $this->manager->createAnthropicDriver($config),
            'azure' => $this->manager->createAzureDriver($config),
            'bedrock' => $this->manager->createBedrockDriver($config),
            'deepseek' => $this->manager->createDeepseekDriver($config),
            'gemini' => $this->manager->createGeminiDriver($config),
            'groq' => $this->manager->createGroqDriver($config),
            'mistral' => $this->manager->createMistralDriver($config),
            'ollama' => $this->manager->createOllamaDriver($config),
            'openai' => $this->manager->createOpenaiDriver($config),
            'openrouter' => $this->manager->createOpenrouterDriver($config),
            'xai' => $this->manager->createXaiDriver($config),
            default => throw new InvalidArgumentException("AI text provider [{$config['driver']}] is not supported."),
        };
    }
}
