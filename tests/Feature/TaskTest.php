<?php

use App\Models\User;
use App\Models\Task;

it('ログイン済みユーザーが自分のタスクを完了できる', function () {
    $user = User::factory()->create();
    $task = Task::factory()->create([
        'user_id' => $user->id,
        'status' => 'todo',
    ]);

    $response = $this->actingAs($user)
        ->postJson("/app/tasks/{$task->id}/complete");

    $response->assertOk()->assertJson(['ok' => true]);
    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'done']);
});

it('他ユーザーのタスクは完了できない', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();
    $task = Task::factory()->create([
        'user_id' => $owner->id,
        'status' => 'todo',
    ]);

    $response = $this->actingAs($other)
        ->postJson("/app/tasks/{$task->id}/complete");

    $response->assertForbidden();
    $this->assertDatabaseHas('tasks', ['id' => $task->id, 'status' => 'todo']);
});

it('未認証ユーザーはタスクを完了できない', function () {
    $task = Task::factory()->create(['status' => 'todo']);

    $response = $this->postJson("/app/tasks/{$task->id}/complete");

    $response->assertUnauthorized();
});
