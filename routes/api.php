<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ChatController;

// 疎通確認（必要なければ後で消してOK）
Route::get('/ping', fn () => response()->json(['ok' => true]));

// 猫GPT
Route::post('/chat', [ChatController::class, 'chat']);
