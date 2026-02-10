<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MainPageController extends Controller
{
    public function index(Request $request)
    {
        // Use the existing index view contents as a backup; render the new pages/landing view.
        return view('pages.landing');
    }
}
