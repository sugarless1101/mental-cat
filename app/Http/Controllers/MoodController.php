<?php

namespace App\Http\Controllers;

use App\Models\MoodLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class MoodController extends Controller
{
    /**
     * Store or update today's mood (upsert by date).
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate(['mood' => 'required|in:good,neutral,bad']);

        $user = $request->user();
        $mood = $request->input('mood');
        // Round logged_at to minute (seconds=0) and upsert per (user_id, logged_at)
        $loggedAt = now()->second(0)->microsecond(0);

        $log = MoodLog::updateOrCreate(
            ['user_id' => $user->id, 'logged_at' => $loggedAt],
            ['mood' => $mood]
        );

        return response()->json(['ok' => true, 'mood' => $log->mood, 'logged_at' => $log->logged_at]);
    }
}
