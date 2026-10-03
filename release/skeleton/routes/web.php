<?php

use App\Http\Controllers\HomeController;
use System\Routing\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');
