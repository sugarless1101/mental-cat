<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\MoodLog;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Minimal data provisioning mirroring previous CatController@app
        $todayMood = $user ? $user->moodLogs()->whereDate('logged_at', today())->latest('logged_at')->first() : null;
        $tasks = $user ? $user->tasks()->where('status','todo')->orderBy('created_at','desc')->limit(5)->get() : collect();
        $doneRecent = $user ? $user->tasks()->where('status','done')->latest('done_at')->limit(5)->get() : collect();
        $chatMessages = $user ? $user->chatMessages()->latest('created_at')->limit(10)->get()->reverse() : collect();

        // ユーザー本人の気分ログのみを取得（最新順、上位100件）
        $moodLogs = $user ? $user->moodLogs()->latest('logged_at')->limit(100)->get() : collect();

        return view('pages.dashboard', compact('todayMood','tasks','doneRecent','chatMessages','moodLogs'));
    }
}
