<?php

namespace App\Http\Controllers;

use System\Framework;
use System\Http\Request;

class HomeController extends Controller
{
    public function index(Request $request)
    {
        return $this->view('welcome', [
            'name'             => $request->name ?? 'Developer',
            'phpVersion'       => PHP_VERSION,
            'frameworkVersion' => Framework::version(),
            'appEnv'           => config('app.env', 'local'),
            'memoryUsage'      => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
        ]);
    }

    public function demo()
    {
        return response([
            'framework'   => 'Bongo PHP Framework',
            'version'     => Framework::version(),
            'status'      => 'online',
            'message'     => 'Core framework route & controller executed in < 1ms with zero external dependencies.',
            'php_version' => PHP_VERSION,
            'memory_peak' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB',
            'environment' => config('app.env', 'local'),
            'timestamp'   => date('Y-m-d H:i:s T'),
            'ecosystem'   => [
                'official_docs'   => 'https://bongo.nobotech.org',
                'packages_hub'    => 'https://bongo.nobotech.org/packages',
                'benchmarks'      => 'https://bongo.nobotech.org/benchmarks',
                'core_github'     => 'https://github.com/Solayman/bongo-php',
                'packages_github' => 'https://github.com/Solayman/bongo-php-packages',
            ],
        ]);
    }
}
