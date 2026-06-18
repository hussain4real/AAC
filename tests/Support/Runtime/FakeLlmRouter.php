<?php

namespace Tests\Support\Runtime;

use App\Support\Runtime\Contracts\LlmRouter;
use App\Support\Runtime\LlmCompletion;
use App\Support\Runtime\LlmRequest;
use App\Support\Runtime\LlmUsage;
use RuntimeException;

/**
 * A deterministic {@see LlmRouter} for runtime tests: completions are scripted
 * up front and returned in order. When the script is exhausted the last
 * completion repeats, which lets loop-guard tests (step limits) run freely.
 */
class FakeLlmRouter implements LlmRouter
{
    /**
     * The scripted completions, returned in order.
     *
     * @var array<int, LlmCompletion>
     */
    public array $queue = [];

    /**
     * The requests received, in order, for assertions.
     *
     * @var array<int, LlmRequest>
     */
    public array $requests = [];

    private int $index = 0;

    /**
     * Script a final-text completion.
     */
    public function textThen(string $text, int $tokensIn = 120, int $tokensOut = 60): self
    {
        $this->queue[] = LlmCompletion::text($text, new LlmUsage($tokensIn, $tokensOut));

        return $this;
    }

    /**
     * Script a tool-call completion.
     *
     * @param  array<string, mixed>  $arguments
     */
    public function toolCallThen(string $tool, array $arguments = [], int $tokensIn = 100, int $tokensOut = 20): self
    {
        $this->queue[] = LlmCompletion::toolCall($tool, $arguments, new LlmUsage($tokensIn, $tokensOut));

        return $this;
    }

    /**
     * Produce the next scripted completion.
     */
    public function complete(LlmRequest $request): LlmCompletion
    {
        $this->requests[] = $request;

        if ($this->queue === []) {
            throw new RuntimeException('FakeLlmRouter has no scripted completions.');
        }

        $completion = $this->queue[$this->index] ?? $this->queue[array_key_last($this->queue)];

        if (array_key_exists($this->index, $this->queue)) {
            $this->index++;
        }

        return $completion;
    }
}
