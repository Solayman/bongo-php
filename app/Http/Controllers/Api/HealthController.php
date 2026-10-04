<?php

namespace App\Http\Controllers\Api;

class HealthController
{
    public function index(): array
    {
        return [
            'status' => 'ok',
            'app'    => config('app.name'),
            'time'   => date(DATE_ATOM),
        ];
    }
}
