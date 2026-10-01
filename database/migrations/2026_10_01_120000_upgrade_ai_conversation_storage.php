<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Migrations\AiMigration;

return new class extends AiMigration
{
    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());
        $messages = config('ai.conversations.tables.messages', 'agent_conversation_messages');
        $conversations = config('ai.conversations.tables.conversations', 'agent_conversations');
        $db = DB::connection($this->getConnection());

        if ($schema->hasColumn($messages, 'approval_state')) {
            $db->table($messages)->whereNotNull('approval_state')->where('approval_state', '!=', 'null')->orderBy('id')->chunk(100, function ($rows) {
                foreach ($rows as $row) {
                    $approval = $this->decoded($row->approval_state);
                    if (! array_key_exists('pending', $approval) || ! is_array($approval['pending'])) {
                        throw new RuntimeException('AI approval state must contain an array of pending approvals.');
                    }
                    if ($approval['pending'] !== []) {
                        throw new RuntimeException('Resolve pending AI tool approvals before upgrading conversation storage.');
                    }
                }
            });
        }

        $db->table($messages)->orderBy('id')->chunk(100, function ($rows) {
            foreach ($rows as $row) {
                foreach ($this->decoded($row->tool_calls) as $call) {
                    if (! is_array($call) || ! is_string($call['id'] ?? null) || ! is_string($call['name'] ?? null) || ! is_array($call['arguments'] ?? null)) {
                        throw new RuntimeException('AI conversation tool calls must contain an ID, name, and arguments.');
                    }
                }
                foreach ($this->decoded($row->tool_results) as $result) {
                    if (! is_array($result) || ! is_string($result['id'] ?? null)) {
                        throw new RuntimeException('AI conversation tool results must contain an ID.');
                    }
                }
                $this->decoded($row->meta);
                $this->decoded($row->usage);
            }
        });

        $db->table($messages)->select('conversation_id')->distinct()->orderBy('conversation_id')->chunk(100, function ($conversations) use ($db, $messages) {
            foreach ($conversations as $conversation) {
                $callIds = [];
                $rows = $db->table($messages)->where('conversation_id', $conversation->conversation_id)->where('role', 'assistant')->orderBy('id')->get();
                $this->assertUnambiguousResults($rows->flatMap(fn (object $row) => $this->decoded($row->tool_results))->all());
                foreach ($rows as $row) {
                    foreach ($this->decoded($row->tool_calls) as $call) {
                        $this->assertUniqueCallId($call['id'], $callIds);
                    }
                }
            }
        });

        foreach ([$conversations, $messages] as $table) {
            if (! $schema->hasColumn($table, 'participant_type')) {
                $schema->table($table, function (Blueprint $blueprint) {
                    $blueprint->string('participant_type')->nullable();
                    $blueprint->unsignedBigInteger('participant_id')->nullable();
                });
            }
            if ($schema->hasColumn($table, 'user_id')) {
                $db->table($table)->whereNotNull('user_id')->whereNull('participant_type')->update([
                    'participant_type' => (new User)->getMorphClass(),
                    'participant_id' => DB::raw('user_id'),
                ]);
            }
        }

        $schema->table($messages, function (Blueprint $blueprint) {
            $blueprint->longText('steps')->nullable();
            $blueprint->string('status', 25)->default('completed');
            $blueprint->text('tool_calls')->nullable()->change();
            $blueprint->text('tool_results')->nullable()->change();
        });

        $db->table($messages)->where('role', '!=', 'assistant')->update(['steps' => '[]']);
        $db->table($messages)->select('conversation_id')->distinct()->orderBy('conversation_id')->chunk(100, function ($conversations) use ($db, $messages) {
            foreach ($conversations as $conversation) {
                $rows = $db->table($messages)->where('conversation_id', $conversation->conversation_id)->where('role', 'assistant')->orderBy('id')->get();
                $results = $rows->flatMap(fn (object $row) => $this->decoded($row->tool_results))->keyBy('id');

                foreach ($rows as $row) {
                    $meta = $this->decoded($row->meta);
                    $calls = collect($this->decoded($row->tool_calls))->map(function (array $call) use ($results): array {
                        $result = $results->get($call['id'] ?? '');

                        return $result === null ? $call : [...$call, 'result' => $result['result'] ?? null, ...array_filter([
                            'denied' => $result['denied'] ?? false,
                            'failed' => $result['failed'] ?? false,
                        ])];
                    })->values()->all();
                    $content = (string) $row->content;
                    $steps = $calls !== [] && $content !== ''
                        ? [$this->step('', $calls), $this->step($content, [], $meta['reasoning'] ?? '')]
                        : [$this->step($content, $calls, $meta['reasoning'] ?? '')];
                    $db->table($messages)->where('id', $row->id)->update([
                        'steps' => json_encode($steps, JSON_THROW_ON_ERROR),
                    ]);
                }
            }
        });

        $db->table($messages)->orderBy('id')->chunk(100, function ($rows) use ($db, $messages) {
            foreach ($rows as $row) {
                $usage = $this->decoded($row->usage);
                if (array_key_exists('prompt_tokens', $usage)) {
                    $usage['input_tokens'] = $usage['input_tokens'] ?? ($usage['prompt_tokens'] + ($usage['cache_read_input_tokens'] ?? 0) + ($usage['cache_write_input_tokens'] ?? 0));
                }
                if (array_key_exists('completion_tokens', $usage)) {
                    $usage['output_tokens'] = $usage['output_tokens'] ?? ($usage['completion_tokens'] + ($usage['reasoning_tokens'] ?? 0));
                }
                $db->table($messages)->where('id', $row->id)->update(['usage' => json_encode($usage, JSON_THROW_ON_ERROR)]);
            }
        });

        $schema->table($messages, function (Blueprint $blueprint) use ($messages) {
            $blueprint->longText('steps')->nullable(false)->change();
            $blueprint->index(['participant_type', 'participant_id', 'agent'], $messages.'_ai_participant_agent');
        });
        $schema->table($conversations, function (Blueprint $blueprint) use ($conversations) {
            $blueprint->index(['participant_type', 'participant_id', 'updated_at'], $conversations.'_ai_participant_updated');
        });
    }

    public function down(): void
    {
        $schema = Schema::connection($this->getConnection());
        $messages = config('ai.conversations.tables.messages', 'agent_conversation_messages');
        $conversations = config('ai.conversations.tables.conversations', 'agent_conversations');
        $db = DB::connection($this->getConnection());

        if ($db->table($messages)->where('status', '!=', 'completed')->exists()) {
            throw new RuntimeException('Resolve incomplete AI turns before rolling back conversation storage.');
        }

        foreach ([$messages, $conversations] as $table) {
            if ($schema->hasColumn($table, 'user_id') && $db->table($table)->whereNotNull('participant_type')->where('participant_type', '!=', (new User)->getMorphClass())->exists()) {
                throw new RuntimeException('Non-user AI participants cannot be represented by legacy conversation storage.');
            }
        }

        // Admit every row before changing ownership, payloads, or schema.
        $db->table($messages)->select('conversation_id')->distinct()->orderBy('conversation_id')->chunk(100, function ($conversations) use ($db, $messages) {
            foreach ($conversations as $conversation) {
                $rows = $db->table($messages)->where('conversation_id', $conversation->conversation_id)->orderBy('id')->get();
                $results = $rows->where('role', 'assistant')->flatMap(fn (object $row) => $this->decoded($row->tool_results));
                $this->assertUnambiguousResults($results->all());
                $results = $results->keyBy('id')->all();
                $callIds = [];
                foreach ($rows as $row) {
                    $this->assertLegacyRollbackHistory($row, $results);
                    foreach ($this->decoded($row->steps) as $step) {
                        foreach ($step['tool_calls'] as $call) {
                            $this->assertUniqueCallId($call['id'], $callIds);
                        }
                    }
                    $this->decoded($row->meta);
                    $this->decoded(json_encode($this->legacyUsage($this->decoded($row->usage)), JSON_THROW_ON_ERROR));
                }
            }
        });
        foreach ([$messages, $conversations] as $table) {
            if ($schema->hasColumn($table, 'user_id')) {
                $db->table($table)->update(['user_id' => DB::raw('participant_id')]);
            }
        }

        $db->table($messages)->orderBy('id')->chunk(100, function ($rows) use ($db, $messages) {
            foreach ($rows as $row) {
                // Preserve original historical payloads; reconstruct only messages written by the new SDK.
                if ($row->tool_calls !== null && $row->tool_results !== null) {
                    continue;
                }
                $calls = [];
                $results = [];
                foreach ($this->decoded($row->steps) as $step) {
                    foreach ($step['tool_calls'] ?? [] as $call) {
                        if (array_key_exists('result', $call)) {
                            $results[] = ['id' => $call['id'], 'name' => $call['name'], 'result' => $call['result'], ...array_filter([
                                'denied' => $call['denied'] ?? false,
                                'failed' => $call['failed'] ?? false,
                            ])];
                        }
                        unset($call['result'], $call['denied'], $call['failed']);
                        $calls[] = $call;
                    }
                }
                $steps = $this->decoded($row->steps);
                $db->table($messages)->where('id', $row->id)->update([
                    'tool_calls' => json_encode($calls, JSON_THROW_ON_ERROR),
                    'tool_results' => json_encode($results, JSON_THROW_ON_ERROR),
                    'usage' => json_encode($this->legacyUsage($this->decoded($row->usage)), JSON_THROW_ON_ERROR),
                    'meta' => json_encode([...$this->decoded($row->meta), 'reasoning' => ($steps[array_key_last($steps)]['reasoning'] ?? '')], JSON_THROW_ON_ERROR),
                ]);
            }
        });

        $schema->table($messages, function (Blueprint $blueprint) use ($messages) {
            $blueprint->dropIndex($messages.'_ai_participant_agent');
            $blueprint->dropColumn(['steps', 'status']);
            $blueprint->text('tool_calls')->nullable(false)->change();
            $blueprint->text('tool_results')->nullable(false)->change();
        });
        $schema->table($conversations, function (Blueprint $blueprint) use ($conversations) {
            $blueprint->dropIndex($conversations.'_ai_participant_updated');
        });
    }

    /** @param  array<array-key, mixed>  $results */
    protected function assertUnambiguousResults(array $results): void
    {
        $seen = [];
        foreach ($results as $result) {
            if (! is_array($result) || ! is_string($result['id'] ?? null)) {
                throw new RuntimeException('AI conversation tool results must contain an ID.');
            }
            if (isset($seen[$result['id']]) && $seen[$result['id']] !== $result) {
                throw new RuntimeException('Preserve ambiguous AI tool results before changing conversation storage.');
            }
            $seen[$result['id']] = $result;
        }
    }

    /** @param  array<string, bool>  $seen */
    protected function assertUniqueCallId(string $id, array &$seen): void
    {
        if (isset($seen[$id])) {
            throw new RuntimeException('Preserve ambiguous AI tool-call IDs before changing conversation storage.');
        }
        $seen[$id] = true;
    }

    /** @param  array<string, array<string, mixed>>  $legacyResults */
    protected function assertLegacyRollbackHistory(stdClass $row, array $legacyResults): void
    {
        $steps = $this->decoded($row->steps);
        if (! array_is_list($steps)) {
            throw new RuntimeException('AI conversation steps must contain a list.');
        }

        $normalized = [];
        $calls = [];
        $callIds = [];
        foreach ($steps as $step) {
            if (! is_array($step) || array_diff(array_keys($step), ['content', 'tool_calls', 'reasoning', 'replay_blocks', 'provider_tool_calls']) !== []
                || ! is_string($step['content'] ?? null) || ! is_string($step['reasoning'] ?? null)
                || ! is_array($step['tool_calls'] ?? null) || ! array_is_list($step['tool_calls'])) {
                throw new RuntimeException('Preserve unrepresentable SDK v1 history before rolling back conversation storage.');
            }
            if (($step['provider_tool_calls'] ?? []) !== [] || ($step['replay_blocks'] ?? []) !== []) {
                throw new RuntimeException('Preserve SDK v1 provider-tool and replay history before rolling back conversation storage.');
            }
            foreach ($step['tool_calls'] as $call) {
                if (! is_array($call) || ! is_string($call['id'] ?? null) || ! is_string($call['name'] ?? null)
                    || ! is_array($call['arguments'] ?? null)
                    || in_array($call['id'], $callIds, true)
                    || ((array_key_exists('denied', $call) || array_key_exists('failed', $call)) && ! array_key_exists('result', $call))
                    || (array_key_exists('denied', $call) && $call['denied'] !== true)
                    || (array_key_exists('failed', $call) && $call['failed'] !== true)) {
                    throw new RuntimeException('Preserve unrepresentable SDK v1 tool history before rolling back conversation storage.');
                }
                $calls[] = $call;
                $callIds[] = $call['id'];
            }
            $normalized[] = $this->step($step['content'], $step['tool_calls'], $step['reasoning']);
        }

        if ($row->role !== 'assistant') {
            $expected = [];
        } else {
            $reasoning = $steps[array_key_last($steps)]['reasoning'] ?? '';
            $meta = $this->decoded($row->meta);
            if ($row->tool_calls !== null && $row->tool_results !== null) {
                $reasoning = $meta['reasoning'] ?? '';
                $calls = collect($this->decoded($row->tool_calls))->map(function (array $call) use ($legacyResults): array {
                    $result = $legacyResults[$call['id']] ?? null;

                    return $result === null ? $call : [...$call, 'result' => $result['result'] ?? null, ...array_filter([
                        'denied' => $result['denied'] ?? false,
                        'failed' => $result['failed'] ?? false,
                    ])];
                })->values()->all();
            }
            $content = (string) $row->content;
            $expected = $calls !== [] && $content !== ''
                ? [$this->step('', $calls), $this->step($content, [], $reasoning)]
                : [$this->step($content, $calls, $reasoning)];
            if (($row->tool_calls === null || $row->tool_results === null) && array_key_exists('reasoning', $meta) && $meta['reasoning'] !== $reasoning) {
                throw new RuntimeException('Preserve conflicting SDK v1 reasoning history before rolling back conversation storage.');
            }
        }

        if ($normalized !== $expected) {
            throw new RuntimeException('Preserve SDK v1 step boundaries before rolling back conversation storage.');
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $calls
     * @return array<string, mixed>
     */
    protected function step(string $content, array $calls = [], string $reasoning = ''): array
    {
        return ['content' => $content, 'tool_calls' => $calls, 'reasoning' => $reasoning, 'replay_blocks' => [], 'provider_tool_calls' => []];
    }

    /**
     * @param  array<string, mixed>  $usage
     * @return array<string, mixed>
     */
    protected function legacyUsage(array $usage): array
    {
        if (array_key_exists('input_tokens', $usage)) {
            $usage['prompt_tokens'] = $usage['input_tokens'] - ($usage['cache_read_input_tokens'] ?? 0) - ($usage['cache_write_input_tokens'] ?? 0);
            unset($usage['input_tokens']);
        }
        if (array_key_exists('output_tokens', $usage)) {
            $usage['completion_tokens'] = $usage['output_tokens'] - ($usage['reasoning_tokens'] ?? 0);
            unset($usage['output_tokens']);
        }

        return $usage;
    }

    /** @return array<array-key, mixed> */
    protected function decoded(?string $json): array
    {
        $decoded = json_decode($json ?? '[]', true, flags: JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new RuntimeException('AI conversation JSON must contain an array.');
        }

        return $decoded;
    }
};
