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
    DB::table('upgrade_messages')->insert(['id' => 'new-answer', 'conversation_id' => $id, 'participant_type' => $participant, 'participant_id' => 7, 'agent' => 'TestAgent', 'role' => 'assistant', 'content' => 'New answer', 'attachments' => '[]', 'usage' => '{"input_tokens":10,"output_tokens":4,"cache_read_input_tokens":2,"cache_write_input_tokens":3,"reasoning_tokens":1}', 'meta' => '{}', 'status' => 'completed', 'steps' => '[{"content":"New answer","reasoning":"New reasoning","tool_calls":[{"id":"new-call","name":"lookup","arguments":{"q":"new"},"result":"ok"}],"replay_blocks":[],"provider_tool_calls":[]}]']);
    $migration->down();
    $answer = DB::table('upgrade_messages')->find('new-answer');
    expect(json_decode($answer->tool_calls, true)[0]['arguments'])->toBe(['q' => 'new'])
        ->and(json_decode($answer->tool_results, true)[0]['result'])->toBe('ok')
        ->and(json_decode($answer->usage, true))->toBe(['cache_read_input_tokens' => 2, 'cache_write_input_tokens' => 3, 'reasoning_tokens' => 1, 'prompt_tokens' => 5, 'completion_tokens' => 3])
        ->and(json_decode($answer->meta, true)['reasoning'])->toBe('New reasoning');
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
    expect(DB::table('upgrade_messages')->find($messageId)->steps)->toBe('[]');
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
