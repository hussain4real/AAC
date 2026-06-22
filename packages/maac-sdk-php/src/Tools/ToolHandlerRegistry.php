<?php

declare(strict_types=1);

namespace Maac\Sdk\Tools;

use Closure;

/**
 * The application's local registry of client-side tool handlers, keyed by tool
 * slug. The auto-resume run loop consults it to resolve the handler MAAC's
 * pause is waiting on.
 */
final class ToolHandlerRegistry
{
    /**
     * @var array<string, ToolHandler>
     */
    private array $handlers = [];

    /**
     * Register a handler instance under its declared tool slug.
     */
    public function register(ToolHandler $handler): self
    {
        $this->handlers[$handler->tool()] = $handler;

        return $this;
    }

    /**
     * Register a closure as the handler for a tool slug.
     *
     * @param  Closure(array<string, mixed>, ToolContext): array<string, mixed>  $callback
     */
    public function registerCallable(string $tool, Closure $callback): self
    {
        return $this->register(new CallableToolHandler($tool, $callback));
    }

    /**
     * Whether a handler is registered for the given tool slug.
     */
    public function has(string $tool): bool
    {
        return isset($this->handlers[$tool]);
    }

    /**
     * Resolve the handler for a tool slug, or null when none is registered.
     */
    public function resolve(string $tool): ?ToolHandler
    {
        return $this->handlers[$tool] ?? null;
    }

    /**
     * The slugs of every registered handler.
     *
     * @return array<int, string>
     */
    public function registered(): array
    {
        return array_keys($this->handlers);
    }
}
