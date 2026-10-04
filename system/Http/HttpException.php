<?php

namespace System\Http;

use RuntimeException;

class HttpException extends RuntimeException
{
    public function __construct(protected int $status, string $message = '')
    {
        parent::__construct($message ?: self::defaultMessage($status), $status);
    }

    public function status(): int
    {
        return $this->status;
    }

    public static function defaultMessage(int $status): string
    {
        return match ($status) {
            403 => 'Forbidden',
            404 => 'Page Not Found',
            405 => 'Method Not Allowed',
            419 => 'Page Expired',
            429 => 'Too Many Requests',
            default => 'Server Error',
        };
    }
}
