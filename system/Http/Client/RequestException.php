<?php
namespace System\Http\Client;

/** Thrown by $response->throw() for a 4xx/5xx answer; ->response holds it */
class RequestException extends \RuntimeException
{
    public function __construct(string $message, public Response $response)
    {
        parent::__construct($message, $response->status());
    }
}
