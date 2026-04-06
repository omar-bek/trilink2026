<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(): View
    {
        return view('welcome');
    }

    public function login(): View
    {
        return view('welcome'); // placeholder until login view exists
    }
}
