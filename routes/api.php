<?php

use System\Routing\Route;

/*
 * API routes: loaded with the "/api" prefix, the "api." name prefix and the
 * "api" middleware group (CORS, no CSRF, no session-based redirects).
 * Return an array from a route to send JSON.
 */

Route::get('/health', function () {
    return [
        'status' => 'ok',
        'app'    => config('app.name'),
        'time'   => date(DATE_ATOM),
    ];
})->name('health');
