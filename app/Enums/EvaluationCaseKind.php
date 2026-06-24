<?php

namespace App\Enums;

/**
 * The workflow an evaluation case exercises. The golden datasets cover the full
 * runtime surface — a no-tool answer, a client-side tool (serviced from the
 * case's stubbed result), a remote HTTP tool, an MCP connector tool, and a
 * knowledge-retrieval (RAG) tool — so a regression in any execution path is
 * caught before an agent is promoted.
 */
enum EvaluationCaseKind: string
{
    case NoTool = 'no_tool';
    case ClientTool = 'client_tool';
    case RemoteTool = 'remote_tool';
    case Connector = 'connector';
    case Rag = 'rag';

    /**
     * Get the human-readable label for the case kind.
     */
    public function label(): string
    {
        return match ($this) {
            self::NoTool => 'No tool',
            self::ClientTool => 'Client-side tool',
            self::RemoteTool => 'Remote HTTP tool',
            self::Connector => 'MCP connector',
            self::Rag => 'Knowledge retrieval (RAG)',
        };
    }

    /**
     * Whether the case expects the agent to call a client-side tool the runtime
     * pauses for (and which the evaluation services from the case's stub).
     */
    public function usesClientTool(): bool
    {
        return $this === self::ClientTool;
    }

    /**
     * Get all case kinds as value/label option pairs.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $case): array => ['value' => $case->value, 'label' => $case->label()],
            self::cases(),
        );
    }
}
