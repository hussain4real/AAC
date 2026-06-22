<?php

declare(strict_types=1);

namespace Maac\Sdk\Resources;

/**
 * The SDK manifest an application fetches from MAAC: the agents it may invoke
 * and the client-side tool contracts it must implement, in a given environment.
 */
final class Manifest
{
    /**
     * @param  array<string, mixed>  $application
     * @param  array<int, ManifestAgent>  $agents
     * @param  array<int, ManifestTool>  $tools
     */
    public function __construct(
        public readonly array $application,
        public readonly string $environment,
        public readonly array $agents,
        public readonly array $tools,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $application = is_array($data['application'] ?? null) ? $data['application'] : [];
        $agents = is_array($data['agents'] ?? null) ? $data['agents'] : [];
        $tools = is_array($data['tools'] ?? null) ? $data['tools'] : [];

        return new self(
            application: $application,
            environment: (string) ($application['environment'] ?? ''),
            agents: array_values(array_map(
                static fn (array $agent): ManifestAgent => ManifestAgent::fromArray($agent),
                array_filter($agents, 'is_array'),
            )),
            tools: array_values(array_map(
                static fn (array $tool): ManifestTool => ManifestTool::fromArray($tool),
                array_filter($tools, 'is_array'),
            )),
        );
    }

    /**
     * Find a tool contract by its slug, or null if the application has none.
     */
    public function tool(string $name): ?ManifestTool
    {
        foreach ($this->tools as $tool) {
            if ($tool->name === $name) {
                return $tool;
            }
        }

        return null;
    }

    /**
     * Find a published agent by its slug, or null.
     */
    public function agent(string $slug): ?ManifestAgent
    {
        foreach ($this->agents as $agent) {
            if ($agent->slug === $slug) {
                return $agent;
            }
        }

        return null;
    }
}
