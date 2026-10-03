<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\Shop\AccountController;
use App\Http\Controllers\Shop\CartController;
use App\Http\Controllers\Shop\CatalogController;
use App\Http\Controllers\Shop\CheckoutController;
use App\Http\Controllers\Shop\VerifyEmailController;
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

    // Forgot password
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email')->middleware('throttle:6,1');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('password.update')->middleware('throttle:6,1');
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
        Route::get('/{product}/edit', [ProductController::class, 'edit'])->name('edit')->where('product', '\d+');
        Route::put('/{product}', [ProductController::class, 'update'])->name('update')->where('product', '\d+');
        Route::delete('/{product}', [ProductController::class, 'destroy'])->name('destroy')->where('product', '\d+');
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
    Route::get('/system', [SystemController::class, 'index'])->name('system')->middleware('can:manage-system');
    Route::post('/system/log-test', [SystemController::class, 'logTest'])->name('system.log-test')->middleware('can:manage-system');
    Route::post('/system/upload-test', [SystemController::class, 'uploadTest'])->name('system.upload-test')->middleware('can:manage-system');
    Route::get('/system/error-test', [SystemController::class, 'errorTest'])->name('system.error-test');
    Route::get('/system/dump-test', [SystemController::class, 'dumpTest'])->name('system.dump-test');

});

// Storefront for customers (the "customer" guard; separate from the admin login)
Route::prefix('/shop')->name('shop.')->group(function () {
    Route::get('/', [CatalogController::class, 'index'])->name('index');
    Route::get('/products/{product}', [CatalogController::class, 'show'])->name('product')->where('product', '\d+');

    Route::get('/cart', [CartController::class, 'show'])->name('cart');
    Route::post('/cart', [CartController::class, 'add'])->name('cart.add');
    Route::patch('/cart/{product}', [CartController::class, 'update'])->name('cart.update')->where('product', '\d+');
    Route::delete('/cart/{product}', [CartController::class, 'remove'])->name('cart.remove')->where('product', '\d+');

    Route::middleware('guest:customer')->group(function () {
        Route::get('/login', [AccountController::class, 'showLogin'])->name('login');
        Route::post('/login', [AccountController::class, 'login']);
        Route::get('/register', [AccountController::class, 'showRegister'])->name('register');
        Route::post('/register', [AccountController::class, 'register'])->middleware('throttle:10,1');
    });

    // The link from the verification email: works even when signed out, as long as it is unchanged and fresh
    Route::get('/email/verify/{id}', [VerifyEmailController::class, 'verify'])->name('verification.verify')->where('id', '\d+')->middleware('signed');

    Route::middleware('auth:customer')->group(function () {
        Route::post('/logout', [AccountController::class, 'logout'])->name('logout');
        Route::get('/email/verify', [VerifyEmailController::class, 'notice'])->name('verification.notice');
        Route::post('/email/verify', [VerifyEmailController::class, 'resend'])->name('verification.send')->middleware('throttle:3,1');

        // Checkout and orders need a verified email address
        Route::middleware('verified:customer,shop.verification.notice')->group(function () {
            Route::get('/checkout', [CheckoutController::class, 'show'])->name('checkout');
            Route::post('/checkout', [CheckoutController::class, 'place'])->name('checkout.place');
            Route::get('/orders', [AccountController::class, 'orders'])->name('orders');
            Route::get('/orders/{order}', [AccountController::class, 'order'])->name('orders.show')->where('order', '\d+');
        });
    });
});
