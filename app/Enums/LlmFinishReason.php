<?php

namespace App\Enums;

/**
 * Why the LLM Router stopped producing a turn: either the model returned a
 * final answer, or it requested a tool be executed before it can continue.
 */
enum LlmFinishReason: string
{
    case Stop = 'stop';
    case ToolCall = 'tool_call';
}
