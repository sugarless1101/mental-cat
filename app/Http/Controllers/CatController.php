<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class CatController extends Controller
{
    public function index()
    {
        return view('index'); // resources/views/index.blade.php
    }

    public function chat()
    {
        return view('chat');  // 単体テスト用（不要なら削除OK）
    }
}
