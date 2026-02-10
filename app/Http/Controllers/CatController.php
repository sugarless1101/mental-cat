<?php

namespace App\Http\Controllers;

use App\Models\MoodLog;
use App\Models\ChatMessage;
use App\Models\Task;
use Illuminate\Http\Request;

class CatController extends Controller
{
    public function index()
    {
        return view('index'); // resources/views/index.blade.php
    }

    public function app(Request $request)
    {
        $user = $request->user();
        
        // 今日の気分ログ（最新1件）
        $todayMood = $user->moodLogs()
            ->whereDate('logged_at', today())
            ->latest('logged_at')
            ->first();

        // タスク（todo のみ、上位5件）
        $tasks = $user->tasks()
            ->where('status', 'todo')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // 最近完了したタスク（上位5件）
        $doneRecent = $user->tasks()
            ->where('status', 'done')
            ->latest('done_at')
            ->limit(5)
            ->get();

        // チャットメッセージ（最新10件）
        $chatMessages = $user->chatMessages()
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->reverse();

        // default bgm_key
        $bgmKey = 'calm';

        return view('app', compact('todayMood', 'tasks', 'chatMessages', 'doneRecent', 'bgmKey'));
    }

    public function chat()
    {
        return view('chat');  // 単体テスト用（不要なら削除OK）
    }
}
