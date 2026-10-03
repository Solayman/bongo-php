<?php
namespace System\Broadcasting;

use System\Http\Http;
use System\Log\Log;
use System\Security\Key;

/**
 * Push live updates to open browser pages through `php bongo websocket:serve`.
 *
 *   Broadcast::to('orders')->send('order.created', ['id' => $order->id]);
 *   Broadcast::to("private-user.{$user->id}")->send('notice', ['text' => 'Paid']);
 *
 * Channels starting with "private-" need a token, made on the server for the signed-in user:
 *   <script>socket.subscribe('private-user.5', '{{ channel_token('private-user.5') }}')</script>
 *
 * A broadcast never breaks the request: when the WebSocket server is down it is logged and skipped.
 */
class Broadcast
{
    protected static ?array $fake = null;

    public function __construct(protected array $channels) {}

    public static function to(string ...$channels): static
    {
        return new static($channels);
    }

    /** Send $event with $data to everyone subscribed to these channels. Returns how many sockets got it. */
    public function send(string $event, array $data = []): int
    {
        if (self::$fake !== null) {
            foreach ($this->channels as $channel) {
                self::$fake[] = ['channel' => $channel, 'event' => $event, 'data' => $data];
            }
            return count($this->channels);
        }

        $payload = json_encode(['channels' => $this->channels, 'event' => $event, 'data' => $data, 'time' => time()], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

        try {
            $response = Http::timeout(3)->post(self::serverUrl() . '/broadcast', [
                'payload'   => $payload,
                'signature' => self::sign($payload),
            ]);
            if (!$response->ok()) {
                Log::warning('Broadcast rejected by the WebSocket server', ['status' => $response->status(), 'event' => $event]);
                return 0;
            }
            return (int) $response->json('delivered', 0);
        } catch (\Throwable $e) {
            Log::warning('Broadcast skipped: WebSocket server not reachable (php bongo websocket:serve)', ['event' => $event, 'error' => $e->getMessage()]);
            return 0;
        }
    }

    /** Token that lets a browser subscribe to a private channel until it expires */
    public static function token(string $channel, int $seconds = 3600): string
    {
        $expires = time() + $seconds;
        return $expires . '.' . self::sign("channel|$channel|$expires");
    }

    public static function verifyToken(string $channel, string $token): bool
    {
        [$expires, $signature] = array_pad(explode('.', $token, 2), 2, '');
        return ctype_digit($expires) && (int) $expires >= time()
            && hash_equals(self::sign("channel|$channel|$expires"), $signature);
    }

    public static function sign(string $message): string
    {
        return hash_hmac('sha256', $message, Key::derive('broadcast'));
    }

    /** Where the app reaches the server (inside your network) */
    public static function serverUrl(): string
    {
        $host = (string) config('broadcasting.host', '127.0.0.1');
        $host = $host === '0.0.0.0' ? '127.0.0.1' : $host;
        return 'http://' . $host . ':' . (int) config('broadcasting.port', 6001);
    }

    /** Where browsers connect: BROADCAST_PUBLIC_URL, or ws://<app host>:<port> */
    public static function publicUrl(): string
    {
        $url = (string) config('broadcasting.public_url', '');
        if ($url !== '') {
            return $url;
        }
        $app = parse_url((string) config('app.url', 'http://localhost'));
        $scheme = ($app['scheme'] ?? 'http') === 'https' ? 'wss' : 'ws';
        return "$scheme://" . ($app['host'] ?? 'localhost') . ':' . (int) config('broadcasting.port', 6001);
    }

    // ------------------------------------------------------------------
    // Testing
    // ------------------------------------------------------------------

    /** Record broadcasts instead of sending them */
    public static function fake(): void
    {
        self::$fake = [];
    }

    /** What was broadcast while faked: [['channel', 'event', 'data'], ...] */
    public static function sent(): array
    {
        return self::$fake ?? [];
    }

    public static function reset(): void
    {
        self::$fake = null;
    }
}
