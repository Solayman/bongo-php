<?php

namespace App\Http\Controllers;

use App\Models\User;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'users'        => User::query()->count(),
            'php_version'  => PHP_VERSION,
            'db_driver'    => config('database.default', 'mysql'),
            'queue_driver' => config('queue.default', 'sync'),
            'env'          => config('app.env', 'production'),
        ];

        return $this->view('dashboard.index', compact('stats'));
    }
}
