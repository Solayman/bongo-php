<?php
namespace App\Http\Controllers;

use System\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        return $this->view('welcome', ['name' => $request->name ?? 'developer']);
    }
}
