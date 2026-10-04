<?php
return [
    // Minutes an access token is valid
    'ttl' => (int) env('JWT_TTL', 60),

    // Minutes after a token was issued during which it can still be refreshed (even if expired)
    'refresh_ttl' => (int) env('JWT_REFRESH_TTL', 20160), // 14 days

    // Seconds of clock difference tolerated between servers
    'leeway' => 30,

    // "iss" claim; tokens from another issuer are rejected
    'issuer' => env('JWT_ISSUER', env('APP_URL', 'bongo')),

    // Signing secret: JWT_SECRET in .env, or (if empty) a key derived from APP_KEY
];
