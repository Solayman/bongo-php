<?php

namespace System\Http\Middleware;

use System\Http\Request;

/**
 * Adds CORS headers from config/cors.php and answers OPTIONS preflight requests.
 * Requests from an origin that is not allowed get no CORS headers, so the browser blocks them.
 */
class Cors
{
    public function handle(): void
    {
        $this->addHeaders();
    }

    /** Called by the router for an OPTIONS request to a matching path */
    public function preflight(): void
    {
        if (!$this->addHeaders() || headers_sent()) {
            return;
        }

        $config = (array) config('cors', []);
        header('Access-Control-Allow-Methods: ' . implode(', ', $config['allowed_methods'] ?? []));
        header('Access-Control-Allow-Headers: ' . implode(', ', $config['allowed_headers'] ?? []));
        header('Access-Control-Max-Age: ' . (int) ($config['max_age'] ?? 0));
    }

    /** @return bool whether the origin is allowed */
    protected function addHeaders(): bool
    {
        $origin = Request::header('Origin');
        if ($origin === null || headers_sent()) {
            return false;
        }

        $config = (array) config('cors', []);
        $allowed = $config['allowed_origins'] ?? [];
        $credentials = (bool) ($config['supports_credentials'] ?? false);

        if (in_array('*', $allowed, true) && !$credentials) {
            header('Access-Control-Allow-Origin: *');
        } elseif (in_array($origin, $allowed, true) || in_array('*', $allowed, true)) {
            // A specific origin is echoed back; with credentials "*" is not allowed by browsers
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin', false);
        } else {
            return false;
        }

        if ($credentials) {
            header('Access-Control-Allow-Credentials: true');
        }
        if (!empty($config['exposed_headers'])) {
            header('Access-Control-Expose-Headers: ' . implode(', ', $config['exposed_headers']));
        }

        return true;
    }
}
