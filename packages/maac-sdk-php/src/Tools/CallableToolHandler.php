<?php

declare(strict_types=1);

namespace Maac\Sdk\Tools;

use Closure;

/**
 * Adapts a closure into a {@see ToolHandler}, so simple tools can be registered
 * inline without declaring a class.
 */
final class CallableToolHandler implements ToolHandler
{
    /**
     * @param  Closure(array<string, mixed>, ToolContext): array<string, mixed>  $callback
     */
    public function __construct(
        private readonly string $tool,
        private readonly Closure $callback,
    ) {}

    public function tool(): string
    {
        return $this->tool;
    }

    public function handle(array $arguments, ToolContext $context): array
    {
        return ($this->callback)($arguments, $context);
    }
}
