<?php

use System\Routing\Route;

Route::get('/health', fn() => ['status' => 'ok', 'app' => config('app.name'), 'time' => date('c')])->name('health');
