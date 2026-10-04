<?php

namespace System\Http\Middleware;

use System\Http\HttpException;
use System\Security\SignedUrl;

/**
 * "signed": the URL must be one made by signed_route() / SignedUrl::make(), unchanged and not expired.
 */
class ValidateSignature
{
    public function handle(): void
    {
        if (!SignedUrl::valid()) {
            throw new HttpException(403, 'This link is invalid or has expired.');
        }
    }
}
