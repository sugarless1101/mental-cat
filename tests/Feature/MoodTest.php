<?php

use App\Models\User;

it('ログイン済みユーザーが気分を保存できる', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/mood-log', ['mood' => 'good']);

    $response->assertOk()
        ->assertJson(['ok' => true, 'mood' => 'good']);

    $this->assertDatabaseHas('mood_logs', [
        'user_id' => $user->id,
        'mood' => 'good',
    ]);
});

it('不正な気分値はバリデーションエラーになる', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/mood-log', ['mood' => 'amazing']);

    $response->assertUnprocessable();
});

it('未認証ユーザーは気分を保存できない', function () {
    $response = $this->postJson('/api/mood-log', ['mood' => 'good']);

    $response->assertUnauthorized();
});
