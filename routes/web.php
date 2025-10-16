<?php

// Routes array: "METHOD path" => [Controller, method]
return [
    'GET /' => ['CleanupController', 'dashboard'],
    'POST /cleanup/ajax' => ['CleanupController', 'ajaxHandler'],
    'POST /cleanup/run' => ['CleanupController', 'runAction'],
    'GET /database' => ['DatabaseController', 'index'],
    'POST /database/clean' => ['DatabaseController', 'cleanDatabase'],
];

