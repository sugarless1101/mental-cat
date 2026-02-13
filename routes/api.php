<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\ChatController;

// 疎通確認（必要なければ後で消してOK）
Route::get('/ping', fn () => response()->json(['ok' => true]));

// チャットAPI（公開、認証オプション）
Route::post('/chat', [ChatController::class, 'store'])
    ->name('api.chat.store');

Route::get('/chat/state', [ChatController::class, 'state'])
    ->name('api.chat.state');
