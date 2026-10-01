<?php

// "METHOD /path/{param}" => [Controller, method, [middleware...]]
// Middleware aliases are registered in config/app.php.

return [
    'GET /' => ['HomeController', 'welcome'],

    // Authentication
    'GET /login'   => ['AuthController', 'showLogin', ['guest', 'csrf']],
    'POST /login'  => ['AuthController', 'login', ['guest', 'csrf']],
    'POST /logout' => ['AuthController', 'logout', ['auth', 'csrf']],

    // Signed-in area
    'GET /home'   => ['HomeController', 'home', ['auth']],
    'GET /system' => ['SystemController', 'index', ['auth']],
];
