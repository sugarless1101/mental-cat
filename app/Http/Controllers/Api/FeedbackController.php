<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LlmLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeedbackController extends Controller
{
    /**
     * 猫の返答に👍/👎のフィードバックを保存する
     * POST /api/feedback  { chat_message_id: int, value: "good"|"bad" }
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'chat_message_id' => ['required', 'integer'],
            'value'           => ['required', 'in:good,bad'],
        ]);

        $user = $request->user();

        $log = LlmLog::where('chat_message_id', $request->chat_message_id)
            ->when($user, fn ($q) => $q->where('user_id', $user->id))
            ->first();

        if (!$log) {
            return response()->json(['ok' => false, 'message' => 'Not found'], 404);
        }

        $log->update(['feedback' => $request->value === 'good']);

        return response()->json(['ok' => true]);
    }
}
