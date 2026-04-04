<?php

namespace App\Http\Controllers;

use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    /**
     * Mark a task as done by manual confirmation (AJAX).
     */
    public function completeManual(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();

        if (! $user || $task->user_id !== $user->id) {
            return response()->json(['ok' => false, 'message' => 'Unauthorized'], 403);
        }

        $task->update([
            'status' => 'done',
            'done_at' => now(),
        ]);

        return response()->json(['ok' => true, 'message' => 'えらいにゃ！了解にゃ！']);
    }
}
