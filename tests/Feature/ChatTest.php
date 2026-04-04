<?php

use App\Models\ChatMessage;
use App\Models\Task;
use App\Models\User;
use App\Services\MentalCatAiService;
use Illuminate\Support\Facades\Http;

function fakeChatOk(string $reply = 'にゃ。', string $mood = 'neutral'): void
{
    $body = json_encode([
        'choices' => [
            ['message' => ['content' => json_encode([
                'reply' => $reply,
                'mood_guess' => $mood,
                'memory_summary' => '',
                'tasks_to_add' => [],
                'tasks_to_complete' => [],
                'bgm_key' => 'calm',
            ])]],
        ],
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
    ]);

    Http::fake(['api.openai.com/*' => Http::response($body, 200)]);
}

// ===== /api/chat（ゲスト） =====

it('ゲストが /api/chat にメッセージを送れる', function () {
    fakeChatOk();

    $response = $this->postJson('/api/chat', ['message' => 'こんにちは', 'mood' => 'neutral']);

    $response->assertOk()->assertJsonStructure(['ok', 'reply', 'mood_guess', 'bgm_key', 'tasks']);
    expect($response->json('ok'))->toBeTrue();
});

it('ゲスト時は DB にメッセージが保存されない', function () {
    fakeChatOk();

    $countBefore = ChatMessage::count();
    $this->postJson('/api/chat', ['message' => 'テスト', 'mood' => 'neutral']);

    expect(ChatMessage::count())->toBe($countBefore);
});

// ===== /app/chat（認証済み） =====

it('ログイン済みユーザーが /app/chat にメッセージを送れる', function () {
    $user = User::factory()->create();
    fakeChatOk('こんにゃ。', 'good');

    $response = $this->actingAs($user)
        ->postJson('/app/chat', ['message' => 'こんにちは', 'mood' => 'good']);

    $response->assertOk()->assertJsonPath('ok', true);
    $response->assertJsonStructure(['ok', 'reply', 'chat_message_id', 'mood_guess', 'bgm_key', 'tasks', 'messages']);
});

it('ログイン済みユーザーのメッセージは DB に保存される', function () {
    $user = User::factory()->create();
    fakeChatOk('了解にゃ。', 'bad');

    $this->actingAs($user)
        ->postJson('/app/chat', ['message' => '疲れた', 'mood' => 'bad']);

    $this->assertDatabaseHas('chat_messages', [
        'user_id' => $user->id,
        'role' => 'user',
        'content' => '疲れた',
    ]);
    $this->assertDatabaseHas('chat_messages', [
        'user_id' => $user->id,
        'role' => 'assistant',
        'content' => '了解にゃ。',
    ]);
});

it('__start__ メッセージで既存の todo タスクは削除されず・新規タスクもDBに保存されない', function () {
    // FINAL.md: 気分選択時の tasks_to_add は TaskWidget のみ・DB保存なし
    $user = User::factory()->create();
    Task::factory()->create(['user_id' => $user->id, 'status' => 'todo', 'title' => '古いタスク']);

    $body = json_encode([
        'choices' => [
            ['message' => ['content' => json_encode([
                'reply' => '気分に合わせたにゃ。',
                'mood_guess' => 'good',
                'memory_summary' => '',
                'tasks_to_add' => [['title' => '新しいタスク', 'reason' => 'test']],
                'tasks_to_complete' => [],
                'bgm_key' => 'refresh',
            ])]],
        ],
        'usage' => ['prompt_tokens' => 100, 'completion_tokens' => 50],
    ]);
    Http::fake(['api.openai.com/*' => Http::response($body, 200)]);

    $response = $this->actingAs($user)
        ->postJson('/app/chat', ['message' => '__start__', 'mood' => 'good']);

    // 既存タスクは削除されない
    $this->assertDatabaseHas('tasks', ['user_id' => $user->id, 'title' => '古いタスク', 'status' => 'todo']);

    // AIが提案したタスクはDBに保存されない
    $this->assertDatabaseMissing('tasks', ['user_id' => $user->id, 'title' => '新しいタスク']);

    // レスポンスには提案タスクが含まれる（TaskWidget用）
    $response->assertJsonPath('tasks.todo.0.title', '新しいタスク');
    $response->assertJsonPath('tasks.todo.0.id', null);
});

it('AI 失敗時はフォールバックレスポンスが返る', function () {
    $user = User::factory()->create();
    Http::fake(['api.openai.com/*' => Http::response([], 500)]);

    $response = $this->actingAs($user)
        ->postJson('/app/chat', ['message' => 'こんにちは', 'mood' => 'neutral']);

    $response->assertOk();
    expect($response->json('reply'))->toBe(MentalCatAiService::getFallbackReply());
});

it('不正な mood 値はバリデーションエラーになる', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/app/chat', ['message' => 'こんにちは', 'mood' => 'amazing']);

    $response->assertUnprocessable();
});

it('message なしはバリデーションエラーになる', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/app/chat', ['mood' => 'good']);

    $response->assertUnprocessable();
});

it('未認証ユーザーは /app/chat にアクセスできない', function () {
    $response = $this->postJson('/app/chat', ['message' => 'こんにちは', 'mood' => 'neutral']);
    $response->assertUnauthorized();
});

// ===== GET /app/chat/state =====

it('ログイン済みユーザーが state を取得できる', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/app/chat/state');

    $response->assertOk()->assertJsonStructure(['ok', 'tasks', 'messages']);
});
