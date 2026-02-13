<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MainPageController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CatController;
use Illuminate\Support\Facades\Route;

// 公開ルート：猫UI（認証不要）
Route::get('/', [MainPageController::class, 'index'])->name('home');

// 認証後のダッシュボード
Route::get('/app', [DashboardController::class, 'index'])->middleware(['auth', 'verified'])->name('app');

// チャット（セッション認証で叩く用）
Route::post('/app/chat', [\App\Http\Controllers\Api\ChatController::class, 'store'])
    ->middleware(['auth'])
    ->name('app.chat.store');

Route::get('/app/chat/state', [\App\Http\Controllers\Api\ChatController::class, 'state'])
    ->middleware(['auth'])
    ->name('app.chat.state');

// タスク手動完了（confirm -> APIで完了）
Route::post('/app/tasks/{task}/complete', [\App\Http\Controllers\TaskController::class, 'completeManual'])
    ->middleware(['auth'])
    ->name('app.tasks.complete');

// 気分保存（upsert）
Route::post('/app/mood', [\App\Http\Controllers\MoodController::class, 'store'])
    ->middleware(['auth'])
    ->name('app.mood.store');

// API形式での気分保存（フロントからfetchで叩く用・セッション認証）
Route::post('/api/mood-log', [\App\Http\Controllers\MoodController::class, 'store'])
    ->middleware(['auth'])
    ->name('api.mood_log.store');

// /dashboard は /app にリダイレクト
Route::get('/dashboard', function () {
    return redirect('/app');
})->name('dashboard');

// プロフィール管理
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
