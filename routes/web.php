<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CleanupController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SystemController;
use System\Routing\Route;

/*
 * Web routes: loaded with the "web" middleware group (CSRF protection).
 * Register with Route::get/post/put/patch/delete/options/any/match, then chain
 * ->name(), ->middleware(), ->withoutMiddleware(), ->where().
 */

// Authentication
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // Products
    Route::prefix('/products')->name('products.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])->name('index');
        Route::get('/create', [ProductController::class, 'create'])->name('create');
        Route::post('/', [ProductController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [ProductController::class, 'edit'])->name('edit')->where('id', '\d+');
        Route::put('/{id}', [ProductController::class, 'update'])->name('update')->where('id', '\d+');
        Route::delete('/{id}', [ProductController::class, 'destroy'])->name('destroy')->where('id', '\d+');
    });

    // Categories
    Route::prefix('/categories')->name('categories.')->group(function () {
        Route::get('/', [CategoryController::class, 'index'])->name('index');
        Route::post('/', [CategoryController::class, 'store'])->name('store');
        Route::get('/{id}/edit', [CategoryController::class, 'edit'])->name('edit')->where('id', '\d+');
        Route::put('/{id}', [CategoryController::class, 'update'])->name('update')->where('id', '\d+');
        Route::delete('/{id}', [CategoryController::class, 'destroy'])->name('destroy')->where('id', '\d+');
    });

    // Orders
    Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{id}', [OrderController::class, 'show'])->name('orders.show')->where('id', '\d+');
    Route::patch('/orders/{id}/status', [OrderController::class, 'updateStatus'])->name('orders.status')->where('id', '\d+');

    // Customers
    Route::get('/customers', [CustomerController::class, 'index'])->name('customers.index');
    Route::get('/customers/{id}', [CustomerController::class, 'show'])->name('customers.show')->where('id', '\d+');

    // Profile
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [ProfileController::class, 'updatePassword'])->name('profile.password');

    // System status
    Route::get('/system', [SystemController::class, 'index'])->name('system');
    Route::post('/system/log-test', [SystemController::class, 'logTest'])->name('system.log-test');
    Route::get('/system/error-test', [SystemController::class, 'errorTest'])->name('system.error-test');
    Route::get('/system/dump-test', [SystemController::class, 'dumpTest'])->name('system.dump-test');

    // Sample cleanup tool (its JS sends the CSRF token as an X-CSRF-TOKEN header)
    Route::get('/cleanup', [CleanupController::class, 'dashboard'])->name('cleanup');
    Route::post('/cleanup/ajax', [CleanupController::class, 'ajaxHandler']);
});
