<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\HealthController;
use System\Routing\Route;

/*
 * API routes: loaded with the "/api" prefix, the "api." name prefix and the
 * "api" middleware group (CORS, no CSRF, no sessions needed).
 * Return an array from a route to send JSON.
 */

Route::get('/health', [HealthController::class, 'index'])->name('health');

// JWT authentication
Route::post('/auth/login', [AuthController::class, 'login'])->name('auth.login');
Route::post('/auth/refresh', [AuthController::class, 'refresh'])->name('auth.refresh');

Route::middleware('auth:api')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
});
