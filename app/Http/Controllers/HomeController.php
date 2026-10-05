<?php

namespace App\Http\Controllers;

use System\Framework;

class HomeController extends Controller
{
    public function index()
    {
        return $this->view('welcome', [
            'phpVersion'       => PHP_VERSION,
            'frameworkVersion' => Framework::version(),
        ]);
    }
}
