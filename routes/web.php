<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CatController;

Route::get('/', [CatController::class, 'index'])->name('home');
Route::get('/chat', [CatController::class, 'chat'])->name('chat');
