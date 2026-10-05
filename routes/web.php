<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SystemController;
use App\Http\Controllers\HomeController;
use System\Routing\Route;

/*
 * Web routes: loaded with the "web" middleware group (CSRF protection).
 * Register with Route::get/post/put/patch/delete/options/any/match, then chain
 * ->name(), ->middleware(), ->withoutMiddleware(), ->where().
 */

// Public Welcome Page
Route::get('/', [HomeController::class, 'index'])->name('welcome');

// Authentication
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);

    // Forgot password
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email')->middleware('throttle:6,1');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update')->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // System status
    Route::get('/system', [SystemController::class, 'index'])->name('system')->middleware('can:manage-system');
    Route::post('/system/log-test', [SystemController::class, 'logTest'])->name('system.log-test')->middleware('can:manage-system');
    Route::post('/system/upload-test', [SystemController::class, 'uploadTest'])->name('system.upload-test')->middleware('can:manage-system');
    Route::get('/system/mail/{id}', [SystemController::class, 'mail'])->name('system.mail')->middleware('can:manage-system');
    Route::get('/system/error-test', [SystemController::class, 'errorTest'])->name('system.error-test');
    Route::get('/system/dump-test', [SystemController::class, 'dumpTest'])->name('system.dump-test');
});
