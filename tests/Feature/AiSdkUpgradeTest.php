<?php

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Laravel\Ai\Messages\UserMessage;
use Laravel\Ai\Responses\Data\TextUsage;
use Laravel\Ai\Storage\DatabaseConversationStore;

function legacyAiTables(bool $participantSchema): void
{
    DB::table('agent_conversations')->count();
    config(['ai.conversations.tables.conversations' => 'upgrade_conversations', 'ai.conversations.tables.messages' => 'upgrade_messages']);
    foreach (['upgrade_conversations', 'upgrade_messages'] as $table) {
        Schema::create($table, function (Blueprint $blueprint) use ($table, $participantSchema) {
            $blueprint->string('id')->primary();
            if ($participantSchema) {
                $blueprint->string('participant_type')->nullable();
                $blueprint->unsignedBigInteger('participant_id')->nullable();
            } else {
                $blueprint->unsignedBigInteger('user_id')->nullable();
            }
            if ($table === 'upgrade_conversations') {
                $blueprint->string('title');
            } else {
                $blueprint->string('conversation_id');
                $blueprint->string('agent');
                $blueprint->string('role');
                foreach (['content', 'attachments', 'tool_calls', 'tool_results', 'usage', 'meta'] as $column) {
                    $blueprint->text($column);
                }
                $blueprint->text('approval_state')->nullable();
            }
            $blueprint->timestamps();
        });
    }
}

function aiStorageUpgrade(): object
{
    return require database_path('migrations/2026_10_01_120000_upgrade_ai_conversation_storage.php');
}

it('preserves populated legacy history and allows new SDK writes and rollback', function (bool $participantSchema) {
    legacyAiTables($participantSchema);
    $participant = (new User)->getMorphClass();
    $owner = $participantSchema ? ['participant_type' => $participant, 'participant_id' => 7] : ['user_id' => 7];
    DB::table('upgrade_conversations')->insert(['id' => 'conversation', 'title' => 'History', ...$owner]);
    $original = ['id' => '1', 'conversation_id' => 'conversation', 'agent' => 'TestAgent', 'role' => 'assistant', 'content' => 'Answer', 'attachments' => '[]', 'tool_calls' => '[{"id":"call","name":"lookup","arguments":{"q":"safe"}}]', 'tool_results' => '[]', 'usage' => '{"prompt_tokens":8,"completion_tokens":4,"cache_read_input_tokens":2,"cache_write_input_tokens":3,"reasoning_tokens":1}', 'approval_state' => '{"pending":[]}', 'meta' => '{"reasoning":"Original reasoning"}', ...$owner];
    DB::table('upgrade_messages')->insert($original);
    DB::table('upgrade_messages')->insert([...$original, 'id' => '2', 'content' => '', 'tool_calls' => '[]', 'tool_results' => '[{"id":"call","name":"lookup","result":"denied","denied":true,"failed":true}]']);
    $migration = aiStorageUpgrade();
    $migration->up();
    $row = DB::table('upgrade_messages')->find('1');
    $steps = json_decode($row->steps, true, flags: JSON_THROW_ON_ERROR);
    expect($row->participant_type)->toBe($participant)
        ->and((int) $row->participant_id)->toBe(7)
        ->and($row->tool_calls)->toBe($original['tool_calls'])
        ->and($row->meta)->toBe($original['meta'])
        ->and($steps[0]['tool_calls'][0])->toMatchArray(['arguments' => ['q' => 'safe'], 'result' => 'denied', 'denied' => true, 'failed' => true])
        ->and($steps[1]['content'])->toBe('Answer')
        ->and($steps[1]['reasoning'])->toBe('Original reasoning')
        ->and(TextUsage::fromArray(json_decode($row->usage, true))->inputTokens)->toBe(13)
        ->and(TextUsage::fromArray(json_decode($row->usage, true))->outputTokens)->toBe(5);
    $store = new DatabaseConversationStore;
    $id = $store->storeConversation($participant, 7, 'New conversation');
    $messageId = $store->storeUserMessage($id, $participant, 7, 'TestAgent', new UserMessage('Hello'));
    expect($store->conversationBelongsTo($id, $participant, 7))->toBeTrue()
        ->and($store->conversationBelongsTo($id, 'different-model', 7))->toBeFalse()
        ->and($store->conversationBelongsTo($id, $participant, 8))->toBeFalse();
    DB::table('upgrade_messages')->insert(['id' => 'new-answer', 'conversation_id' => $id, 'participant_type' => $participant, 'participant_id' => 7, 'agent' => 'TestAgent', 'role' => 'assistant', 'content' => 'New answer', 'attachments' => '[]', 'usage' => '{"input_tokens":10,"output_tokens":4,"cache_read_input_tokens":2,"cache_write_input_tokens":3,"reasoning_tokens":1}', 'meta' => '{}', 'status' => 'completed', 'steps' => '[{"content":"","tool_calls":[{"id":"new-call","name":"lookup","arguments":{"q":"new"},"result":"ok"}],"reasoning":"","replay_blocks":[],"provider_tool_calls":[]},{"content":"New answer","tool_calls":[],"reasoning":"0","replay_blocks":[],"provider_tool_calls":[]}]']);
    $supportedSteps = json_decode(DB::table('upgrade_messages')->find('new-answer')->steps, true, flags: JSON_THROW_ON_ERROR);
    $migration->down();
    $answer = DB::table('upgrade_messages')->find('new-answer');
    expect(json_decode($answer->tool_calls, true)[0]['arguments'])->toBe(['q' => 'new'])
        ->and(json_decode($answer->tool_results, true)[0]['result'])->toBe('ok')
        ->and(json_decode($answer->usage, true))->toBe(['cache_read_input_tokens' => 2, 'cache_write_input_tokens' => 3, 'reasoning_tokens' => 1, 'prompt_tokens' => 5, 'completion_tokens' => 3])
        ->and(json_decode($answer->meta, true)['reasoning'])->toBe('0');
    $old = DB::table('upgrade_messages')->find('1');
    $new = DB::table('upgrade_messages')->find($messageId);
    expect($old->tool_calls)->toBe($original['tool_calls'])
        ->and($old->meta)->toBe($original['meta'])
        ->and($new->tool_calls)->toBe('[]')
        ->and($new->tool_results)->toBe('[]');
    if (! $participantSchema) {
        expect((int) $new->user_id)->toBe(7);
    }
    $migration->up();
    expect(DB::table('upgrade_messages')->find($messageId)->steps)->toBe('[]')
        ->and(json_decode(DB::table('upgrade_messages')->find('new-answer')->steps, true, flags: JSON_THROW_ON_ERROR))->toBe($supportedSteps);
})->with([false, true]);

it('upgrades an empty legacy schema for a fresh remembered conversation', function () {
    legacyAiTables(false);
    aiStorageUpgrade()->up();
    $store = new DatabaseConversationStore;
    $participant = (new User)->getMorphClass();
    $id = $store->storeConversation($participant, 9, 'Fresh');
    $messageId = $store->storeUserMessage($id, $participant, 9, 'TestAgent', new UserMessage('Hello'));
    expect(DB::table('upgrade_messages')->find($messageId)->steps)->toBe('[]');
});

it('rejects pending approvals before changing the schema', function () {
    legacyAiTables(false);
    DB::table('upgrade_messages')->insert(['id' => 'pending', 'conversation_id' => 'c', 'user_id' => 7, 'agent' => 'TestAgent', 'role' => 'assistant', 'content' => '', 'attachments' => '[]', 'tool_calls' => '[]', 'tool_results' => '[]', 'usage' => '[]', 'meta' => '[]', 'approval_state' => '{"pending":["call"]}']);
    expect(fn () => aiStorageUpgrade()->up())->toThrow(RuntimeException::class, 'Resolve pending')
        ->and(Schema::hasColumn('upgrade_messages', 'steps'))->toBeFalse();
});

it('rejects corrupt historical JSON before changing the schema', function () {
    legacyAiTables(false);
    DB::table('upgrade_messages')->insert(['id' => 'bad', 'conversation_id' => 'c', 'user_id' => 7, 'agent' => 'TestAgent', 'role' => 'assistant', 'content' => '', 'attachments' => '[]', 'tool_calls' => '{', 'tool_results' => '[]', 'usage' => '[]', 'meta' => '[]']);
    expect(fn () => aiStorageUpgrade()->up())->toThrow(JsonException::class)
        ->and(Schema::hasColumn('upgrade_messages', 'steps'))->toBeFalse();
});

it('rejects malformed approval markers before changing the schema', function () {
    legacyAiTables(false);
    DB::table('upgrade_messages')->insert(['id' => 'bad-marker', 'conversation_id' => 'c', 'user_id' => 7, 'agent' => 'TestAgent', 'role' => 'assistant', 'content' => '', 'attachments' => '[]', 'tool_calls' => '[]', 'tool_results' => '[]', 'usage' => '[]', 'meta' => '[]', 'approval_state' => '{"pending":true}']);
    expect(fn () => aiStorageUpgrade()->up())->toThrow(RuntimeException::class, 'AI approval state')
        ->and(Schema::hasColumn('upgrade_messages', 'steps'))->toBeFalse();
});

it('refuses unrepresentable completed SDK history before changing any rows or schema', function (bool $participantSchema, string $shape) {
    legacyAiTables($participantSchema);
    $participant = (new User)->getMorphClass();
    $owner = $participantSchema ? ['participant_type' => $participant, 'participant_id' => 7] : ['user_id' => 7];
    DB::table('upgrade_conversations')->insert(['id' => 'legacy', 'title' => 'Legacy', ...$owner]);
    DB::table('upgrade_messages')->insert(['id' => 'legacy', 'conversation_id' => 'legacy', 'agent' => 'TestAgent', 'role' => 'assistant', 'content' => 'Legacy reply', 'attachments' => '[]', 'tool_calls' => '[]', 'tool_results' => '[]', 'usage' => '{"prompt_tokens":8}', 'meta' => '{}', ...$owner]);
    $migration = aiStorageUpgrade();
    $migration->up();
    $store = new DatabaseConversationStore;
    $id = $store->storeConversation($participant, 9, 'New conversation');
    $store->storeUserMessage($id, $participant, 9, 'TestAgent', new UserMessage('Hello'));
    $step = ['content' => 'Final reply', 'tool_calls' => [], 'reasoning' => 'Final reasoning', 'replay_blocks' => [], 'provider_tool_calls' => []];
    $providerCall = ['id' => 'provider-call', 'type' => 'web_search', 'data' => ['query' => 'example', 'sources' => [['url' => 'https://example.com']]]];
    $toolCall = ['id' => 'call', 'name' => 'lookup', 'arguments' => ['q' => 'example'], 'result' => 'answer'];
    $steps = match ($shape) {
        'provider' => [[...$step, 'provider_tool_calls' => [$providerCall]]],
        'provider with multiple rounds' => [[...$step, 'content' => 'First thought', 'reasoning' => 'First reasoning', 'provider_tool_calls' => [$providerCall]], [...$step, 'content' => '', 'reasoning' => 'Second reasoning', 'tool_calls' => [$toolCall]], $step],
        'multiple reasoning rounds' => [[...$step, 'content' => '', 'reasoning' => 'First reasoning', 'tool_calls' => [$toolCall]], $step],
        'intermediate content' => [[...$step, 'content' => 'Intermediate reply', 'reasoning' => ''], $step],
        'combined content and tools' => [[...$step, 'tool_calls' => [$toolCall]]],
        'replay' => [[...$step, 'replay_blocks' => [['type' => 'thinking', 'signature' => 'opaque-signature']]]],
        'unknown step data' => [[...$step, 'new_provider_field' => 'retain me']],
        'repeated call IDs' => [[...$step, 'content' => '', 'reasoning' => '', 'tool_calls' => [$toolCall]], $step],
        'non-list steps' => ['unexpected' => $step],
        'invalid tool data' => [[...$step, 'content' => '', 'tool_calls' => [[...$toolCall, 'arguments' => 'invalid']]], $step],
        'conflicting meta reasoning' => [$step],
        'invalid archived result' => [$step],
        'changed archived history' => [$step],
    };
    DB::table('upgrade_messages')->insert(['id' => 'zzz-unrepresentable', 'conversation_id' => $id, 'participant_type' => $participant, 'participant_id' => 9, 'agent' => 'TestAgent', 'role' => 'assistant', 'content' => 'Final reply', 'attachments' => '[]', 'usage' => '{"input_tokens":13,"output_tokens":5}', 'meta' => '{"provider":"test","model":"model"}', 'status' => 'completed', 'steps' => json_encode($steps, JSON_THROW_ON_ERROR)]);
    if ($shape === 'repeated call IDs') {
        $firstSteps = $steps;
        $firstSteps[0]['tool_calls'][0]['result'] = 'first';
        DB::table('upgrade_messages')->insert(['id' => 'aaa-first-call', 'conversation_id' => $id, 'participant_type' => $participant, 'participant_id' => 9, 'agent' => 'TestAgent', 'role' => 'assistant', 'content' => 'Final reply', 'attachments' => '[]', 'usage' => '{"input_tokens":13,"output_tokens":5}', 'meta' => '{}', 'status' => 'completed', 'steps' => json_encode($firstSteps, JSON_THROW_ON_ERROR)]);
    }
    if ($shape === 'invalid archived result') {
        DB::table('upgrade_messages')->where('id', 'zzz-unrepresentable')->update(['tool_results' => '[{"result":"missing ID"}]']);
    }
    if ($shape === 'changed archived history') {
        DB::table('upgrade_messages')->where('id', 'legacy')->update(['steps' => json_encode([[...$step, 'content' => 'Legacy reply', 'reasoning' => 'SDK changed reasoning']], JSON_THROW_ON_ERROR)]);
    }
    if ($shape === 'conflicting meta reasoning') {
        DB::table('upgrade_messages')->where('id', 'zzz-unrepresentable')->update(['meta' => '{"reasoning":"Conflicting reasoning"}']);
    }
    $snapshot = fn () => [
        'messages' => DB::table('upgrade_messages')->orderBy('id')->get()->map(fn (object $row) => (array) $row)->all(),
        'conversations' => DB::table('upgrade_conversations')->orderBy('id')->get()->map(fn (object $row) => (array) $row)->all(),
        'message_columns' => Schema::getColumns('upgrade_messages'),
        'conversation_columns' => Schema::getColumns('upgrade_conversations'),
        'message_indexes' => Schema::getIndexes('upgrade_messages'),
        'conversation_indexes' => Schema::getIndexes('upgrade_conversations'),
    ];
    $before = $snapshot();
    expect(fn () => $migration->down())->toThrow(RuntimeException::class)
        ->and($snapshot())->toBe($before);
})->with([false, true])->with(['provider', 'provider with multiple rounds', 'multiple reasoning rounds', 'intermediate content', 'combined content and tools', 'replay', 'unknown step data', 'repeated call IDs', 'non-list steps', 'invalid tool data', 'conflicting meta reasoning', 'invalid archived result', 'changed archived history']);

it('refuses repeated legacy call IDs before any upgrade writes or schema changes', function (bool $participantSchema) {
    legacyAiTables($participantSchema);
    $participant = (new User)->getMorphClass();
    $owner = $participantSchema ? ['participant_type' => $participant, 'participant_id' => 7] : ['user_id' => 7];
    DB::table('upgrade_conversations')->insert(['id' => 'legacy', 'title' => 'Legacy', ...$owner]);
    foreach (['first', 'second'] as $answer) {
        DB::table('upgrade_messages')->insert(['id' => $answer, 'conversation_id' => 'legacy', 'agent' => 'TestAgent', 'role' => 'assistant', 'content' => $answer, 'attachments' => '[]', 'tool_calls' => '[{"id":"repeated","name":"lookup","arguments":{"q":"example"}}]', 'tool_results' => json_encode([['id' => 'repeated', 'name' => 'lookup', 'result' => $answer]], JSON_THROW_ON_ERROR), 'usage' => '{"prompt_tokens":8}', 'meta' => '{}', ...$owner]);
    }
    $snapshot = fn () => [
        'messages' => DB::table('upgrade_messages')->orderBy('id')->get()->map(fn (object $row) => (array) $row)->all(),
        'conversations' => DB::table('upgrade_conversations')->orderBy('id')->get()->map(fn (object $row) => (array) $row)->all(),
        'message_columns' => Schema::getColumns('upgrade_messages'),
        'conversation_columns' => Schema::getColumns('upgrade_conversations'),
        'message_indexes' => Schema::getIndexes('upgrade_messages'),
        'conversation_indexes' => Schema::getIndexes('upgrade_conversations'),
    ];
    $before = $snapshot();
    expect(fn () => aiStorageUpgrade()->up())->toThrow(RuntimeException::class, 'Preserve ambiguous')
        ->and($snapshot())->toBe($before);
})->with([false, true]);
