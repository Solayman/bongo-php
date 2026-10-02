<?php

// Routes array: "METHOD path" => [Controller, method]
return [
    'GET /' => ['DashboardController', 'index'],
    'GET /dashboard' => ['DashboardController', 'index'],
    'GET /cleanup' => ['CleanupController', 'dashboard'],
    'POST /cleanup/ajax' => ['CleanupController', 'ajaxHandler'],
    'POST /cleanup/run' => ['CleanupController', 'runAction'],
    'GET /database' => ['DatabaseController', 'index'],
    'POST /database/clean' => ['DatabaseController', 'cleanDatabase'],
];

