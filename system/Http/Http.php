<?php

namespace System\Http;

use System\Http\Client\Factory;
use System\Http\Client\PendingRequest;
use System\Http\Client\Response;

/**
 * Call other APIs:
 *   $r = Http::post('https://api.example.com/sms', ['to' => $phone, 'text' => $text]);
 *   $r->ok(); $r->json('id');
 *   Http::withToken($key)->timeout(10)->retry(3, 200)->get($url, ['page' => 2]);
 * In tests: Http::fake([...]) then Http::sent().
 *
 * @method static PendingRequest withHeaders(array $headers)
 * @method static PendingRequest withToken(string $token, string $type = 'Bearer')
 * @method static PendingRequest withBasicAuth(string $user, string $password)
 * @method static PendingRequest asForm()
 * @method static PendingRequest timeout(int $seconds)
 * @method static PendingRequest retry(int $times, int $sleepMs = 0)
 * @method static Response get(string $url, array $query = [])
 * @method static Response post(string $url, array $data = [])
 * @method static Response put(string $url, array $data = [])
 * @method static Response patch(string $url, array $data = [])
 * @method static Response delete(string $url, array $data = [])
 */
class Http
{
    protected static ?Factory $factory = null;

    public static function factory(): Factory
    {
        return self::$factory ??= new Factory();
    }

    public static function __callStatic(string $method, array $args)
    {
        return (new PendingRequest(self::factory()))->$method(...$args);
    }

    /** For tests: answer requests with these responses ([url pattern => Response]), nothing goes out */
    public static function fake(array $responses = []): void
    {
        self::factory()->fake($responses);
    }

    /** A fake response: Http::response(['id' => 7], 201) */
    public static function response(array|string $body = [], int $status = 200, array $headers = []): Response
    {
        $json = is_array($body);
        return new Response(
            $status,
            array_change_key_case($headers + ($json ? ['Content-Type' => 'application/json'] : []), CASE_LOWER),
            $json ? (string) json_encode($body) : $body
        );
    }

    /** Requests sent while faked */
    public static function sent(): array
    {
        return self::factory()->sent();
    }

    /** Back to real requests */
    public static function reset(): void
    {
        self::$factory = null;
    }
}
