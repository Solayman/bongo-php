<?php

// "METHOD /path/{param}" => [Controller, method, [middleware...]]
// Middleware aliases are registered in config/app.php.

$guest = ['guest', 'csrf'];
$admin = ['auth', 'csrf'];

return [
    // Authentication
    'GET /login'  => ['AuthController', 'showLogin', $guest],
    'POST /login' => ['AuthController', 'login', $guest],
    'POST /logout' => ['AuthController', 'logout', $admin],

    // Dashboard
    'GET /'          => ['DashboardController', 'index', $admin],
    'GET /dashboard' => ['DashboardController', 'index', $admin],

    // Products
    'GET /products'               => ['ProductController', 'index', $admin],
    'GET /products/create'        => ['ProductController', 'create', $admin],
    'POST /products'              => ['ProductController', 'store', $admin],
    'GET /products/{id}/edit'     => ['ProductController', 'edit', $admin],
    'POST /products/{id}'         => ['ProductController', 'update', $admin],
    'POST /products/{id}/delete'  => ['ProductController', 'destroy', $admin],

    // Categories
    'GET /categories'              => ['CategoryController', 'index', $admin],
    'POST /categories'             => ['CategoryController', 'store', $admin],
    'GET /categories/{id}/edit'    => ['CategoryController', 'edit', $admin],
    'POST /categories/{id}'        => ['CategoryController', 'update', $admin],
    'POST /categories/{id}/delete' => ['CategoryController', 'destroy', $admin],

    // Orders
    'GET /orders'               => ['OrderController', 'index', $admin],
    'GET /orders/{id}'          => ['OrderController', 'show', $admin],
    'POST /orders/{id}/status'  => ['OrderController', 'updateStatus', $admin],

    // Customers
    'GET /customers'      => ['CustomerController', 'index', $admin],
    'GET /customers/{id}' => ['CustomerController', 'show', $admin],

    // Profile
    'GET /profile'           => ['ProfileController', 'edit', $admin],
    'POST /profile'          => ['ProfileController', 'update', $admin],
    'POST /profile/password' => ['ProfileController', 'updatePassword', $admin],

    // System status (framework self-check)
    'GET /system' => ['SystemController', 'index', $admin],

    // Sample cleanup tool (JSON endpoints, no CSRF token in its JS)
    'GET /cleanup'       => ['CleanupController', 'dashboard', ['auth']],
    'POST /cleanup/ajax' => ['CleanupController', 'ajaxHandler', ['auth']],
];
