<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\LlmLog;
use App\Models\MoodLog;
use App\Models\User;

class AdminController extends Controller
{
    public function index()
    {
        $now = now();
        $startOfMonth = $now->copy()->startOfMonth();

        // LLM使用状況（今月）
        $llmStats = LlmLog::where('created_at', '>=', $startOfMonth)
            ->selectRaw('
                COUNT(*) as total_calls,
                SUM(tokens_in + tokens_out) as total_tokens,
                SUM(cost_estimate) as total_cost,
                AVG(latency_ms) as avg_latency,
                SUM(CASE WHEN ok = 1 THEN 1 ELSE 0 END) * 100.0 / COUNT(*) as success_rate,
                SUM(CASE WHEN ok = 0 THEN 1 ELSE 0 END) as fallback_count
            ')
            ->first();

        // 全期間の統計
        $llmAllTime = LlmLog::selectRaw('COUNT(*) as total_calls, SUM(cost_estimate) as total_cost')->first();

        // ユーザー概況
        $totalUsers = User::count();
        $todayActive = ChatMessage::whereDate('created_at', today())
            ->distinct('user_id')->count('user_id');
        $totalMoodLogs = MoodLog::count();

        return view('pages.admin', compact(
            'llmStats',
            'llmAllTime',
            'totalUsers',
            'todayActive',
            'totalMoodLogs',
        ));
    }
}
