<?php

use App\Http\Controllers\Api\ChatController;
use App\Http\Controllers\Api\FeedbackController;
use Illuminate\Support\Facades\Route;

// 疎通確認（必要なければ後で消してOK）
Route::get('/ping', fn () => response()->json(['ok' => true]));

// チャットAPI（公開、認証オプション）
// レートリミット: 1IPあたり30回/分
Route::middleware('throttle:30,1')->group(function () {
    Route::post('/chat', [ChatController::class, 'store'])
        ->name('api.chat.store');

    Route::get('/chat/state', [ChatController::class, 'state'])
        ->name('api.chat.state');

    Route::post('/feedback', [FeedbackController::class, 'store'])
        ->middleware('auth:sanctum')
        ->name('api.feedback.store');
});
