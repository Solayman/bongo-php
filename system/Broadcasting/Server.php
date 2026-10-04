<?php
namespace System\Broadcasting;

/**
 * The WebSocket server behind `php bongo websocket:serve`. Pure PHP, no extensions.
 *
 * One port does two jobs:
 *   - browsers connect with WebSocket and send {"subscribe": "orders"} (plus "token" for private- channels)
 *   - the app POSTs /broadcast (signed with APP_KEY) and the server forwards it to the subscribers
 *
 * Messages to browsers are JSON: {"channel": "orders", "event": "order.created", "data": {...}}
 */
class Server
{
    protected const MAX_FRAME = 65536;
    protected const MAX_HTTP = 1048576;

    /** @var resource */
    protected $listener;
    /** id => ['socket', 'buffer', 'ws' (bool), 'channels' => [], 'fragments' => ''] */
    protected array $clients = [];
    protected bool $running = true;
    /** @var callable(string): void */
    protected $log;

    public function __construct(protected string $host = '127.0.0.1', protected int $port = 6001, protected array $allowedOrigins = [], ?callable $log = null)
    {
        $this->log = $log ?? fn(string $line) => print(date('H:i:s') . " $line\n");
    }

    public function listen(): void
    {
        $this->listener = @stream_socket_server("tcp://{$this->host}:{$this->port}", $errno, $error);
        if (!$this->listener) {
            throw new \RuntimeException("Cannot listen on {$this->host}:{$this->port}: $error");
        }
        stream_set_blocking($this->listener, false);
        ($this->log)("WebSocket server on ws://{$this->host}:{$this->port}");
    }

    /** Run until stop(); $tick runs about every second (used by tests and for housekeeping) */
    public function run(?callable $tick = null): void
    {
        if (!$this->listener) {
            $this->listen();
        }
        while ($this->running) {
            $read = [$this->listener];
            foreach ($this->clients as $client) {
                $read[] = $client['socket'];
            }
            $write = $except = null;
            if (@stream_select($read, $write, $except, 1) === false) {
                break;
            }
            foreach ($read as $socket) {
                $socket === $this->listener ? $this->accept() : $this->receive((int) $socket);
            }
            if ($tick) {
                $tick($this);
            }
        }
        foreach (array_keys($this->clients) as $id) {
            $this->disconnect($id);
        }
        fclose($this->listener);
    }

    public function stop(): void
    {
        $this->running = false;
    }

    /** How many browsers are connected (and subscribed to $channel, when given) */
    public function connections(?string $channel = null): int
    {
        return count(array_filter($this->clients, fn($c) => $c['ws'] && ($channel === null || isset($c['channels'][$channel]))));
    }

    protected function accept(): void
    {
        $socket = @stream_socket_accept($this->listener, 0);
        if ($socket) {
            stream_set_blocking($socket, false);
            $this->clients[(int) $socket] = ['socket' => $socket, 'buffer' => '', 'ws' => false, 'channels' => [], 'fragments' => ''];
        }
    }

    protected function receive(int $id): void
    {
        $data = @fread($this->clients[$id]['socket'], 65536);
        if ($data === '' || $data === false) {
            if (feof($this->clients[$id]['socket'])) {
                $this->disconnect($id);
            }
            return;
        }
        $this->clients[$id]['buffer'] .= $data;

        try {
            $this->clients[$id]['ws'] ? $this->readFrames($id) : $this->readHttp($id);
        } catch (\Throwable $e) {
            ($this->log)('Closed a connection: ' . $e->getMessage());
            $this->disconnect($id);
        }
    }

    // ------------------------------------------------------------------
    // HTTP: the WebSocket handshake, or a broadcast from the app
    // ------------------------------------------------------------------

    protected function readHttp(int $id): void
    {
        $buffer = $this->clients[$id]['buffer'];
        if (strlen($buffer) > self::MAX_HTTP) {
            throw new \LengthException('request too large');
        }
        $end = strpos($buffer, "\r\n\r\n");
        if ($end === false) {
            return;
        }
        $lines = explode("\r\n", substr($buffer, 0, $end));
        [$method, $path] = array_pad(explode(' ', array_shift($lines)), 2, '');
        $headers = [];
        foreach ($lines as $line) {
            [$name, $value] = array_pad(explode(':', $line, 2), 2, '');
            $headers[strtolower(trim($name))] = trim($value);
        }

        if (isset($headers['sec-websocket-key']) && strtolower($headers['upgrade'] ?? '') === 'websocket') {
            $this->clients[$id]['buffer'] = (string) substr($buffer, $end + 4);
            $this->handshake($id, $headers);
            return;
        }

        $length = (int) ($headers['content-length'] ?? 0);
        $body = (string) substr($buffer, $end + 4);
        if (strlen($body) < $length) {
            return;   // wait for the rest of the body
        }

        if ($method === 'POST' && parse_url($path, PHP_URL_PATH) === '/broadcast') {
            [$status, $result] = $this->broadcastRequest(substr($body, 0, $length), $headers['content-type'] ?? '');
        } else {
            [$status, $result] = [200, ['ok' => true, 'connections' => $this->connections()]];
        }
        $json = json_encode($result);
        $this->write($id, "HTTP/1.1 $status " . ($status === 200 ? 'OK' : 'Error') . "\r\nContent-Type: application/json\r\n"
            . 'Content-Length: ' . strlen($json) . "\r\nConnection: close\r\n\r\n$json");
        $this->disconnect($id);
    }

    protected function handshake(int $id, array $headers): void
    {
        $origin = $headers['origin'] ?? '';
        if ($this->allowedOrigins && $origin !== '' && !in_array(rtrim($origin, '/'), $this->allowedOrigins, true)) {
            $this->write($id, "HTTP/1.1 403 Forbidden\r\nConnection: close\r\n\r\n");
            $this->disconnect($id);
            ($this->log)("Refused a browser from $origin (not in BROADCAST_ALLOWED_ORIGINS)");
            return;
        }
        $accept = base64_encode(sha1($headers['sec-websocket-key'] . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
        $this->write($id, "HTTP/1.1 101 Switching Protocols\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Accept: $accept\r\n\r\n");
        $this->clients[$id]['ws'] = true;
        if ($this->clients[$id]['buffer'] !== '') {
            $this->readFrames($id);
        }
    }

    /** @return array{0: int, 1: array} */
    protected function broadcastRequest(string $body, string $contentType): array
    {
        if (str_contains($contentType, 'json')) {
            $input = json_decode($body, true);
        } else {
            parse_str($body, $input);
        }
        $payload = (string) ($input['payload'] ?? '');
        $signature = (string) ($input['signature'] ?? '');

        if ($payload === '' || !hash_equals(Broadcast::sign($payload), $signature)) {
            return [403, ['error' => 'bad signature (is APP_KEY the same for the app and the server?)']];
        }
        $message = json_decode($payload, true);
        if (!is_array($message) || abs(time() - (int) ($message['time'] ?? 0)) > 60) {
            return [403, ['error' => 'expired broadcast']];
        }

        $delivered = 0;
        foreach ((array) ($message['channels'] ?? []) as $channel) {
            $delivered += $this->publish((string) $channel, (string) ($message['event'] ?? ''), $message['data'] ?? []);
        }
        return [200, ['delivered' => $delivered]];
    }

    /** Send an event to every socket subscribed to $channel; returns how many */
    public function publish(string $channel, string $event, mixed $data): int
    {
        $frame = Frame::encode(json_encode(['channel' => $channel, 'event' => $event, 'data' => $data], JSON_UNESCAPED_UNICODE));
        $count = 0;
        foreach ($this->clients as $id => $client) {
            if ($client['ws'] && isset($client['channels'][$channel])) {
                $this->write($id, $frame);
                $count++;
            }
        }
        return $count;
    }

    // ------------------------------------------------------------------
    // WebSocket frames from browsers
    // ------------------------------------------------------------------

    protected function readFrames(int $id): void
    {
        while (isset($this->clients[$id]) && ($frame = Frame::decode($this->clients[$id]['buffer'], self::MAX_FRAME)) !== null) {
            [$fin, $opcode, $payload] = $frame;

            switch ($opcode) {
                case Frame::CLOSE:
                    $this->write($id, Frame::encode('', Frame::CLOSE));
                    $this->disconnect($id);
                    return;
                case Frame::PING:
                    $this->write($id, Frame::encode($payload, Frame::PONG));
                    break;
                case Frame::PONG:
                    break;
                default:
                    // Text (or a continuation of a fragmented message)
                    $this->clients[$id]['fragments'] .= $payload;
                    if (strlen($this->clients[$id]['fragments']) > self::MAX_FRAME) {
                        throw new \LengthException('message too large');
                    }
                    if ($fin) {
                        $message = $this->clients[$id]['fragments'];
                        $this->clients[$id]['fragments'] = '';
                        $this->onMessage($id, $message);
                    }
            }
        }
    }

    protected function onMessage(int $id, string $message): void
    {
        $data = json_decode($message, true);
        if (!is_array($data)) {
            return;
        }
        if (isset($data['subscribe'])) {
            $channel = (string) $data['subscribe'];
            if (str_starts_with($channel, 'private-') && !Broadcast::verifyToken($channel, (string) ($data['token'] ?? ''))) {
                $this->reply($id, ['error' => "Not allowed to subscribe to $channel", 'channel' => $channel]);
                return;
            }
            $this->clients[$id]['channels'][$channel] = true;
            $this->reply($id, ['subscribed' => $channel]);
        } elseif (isset($data['unsubscribe'])) {
            unset($this->clients[$id]['channels'][(string) $data['unsubscribe']]);
        } elseif (isset($data['ping'])) {
            $this->reply($id, ['pong' => true]);
        }
    }

    protected function reply(int $id, array $data): void
    {
        $this->write($id, Frame::encode(json_encode($data)));
    }

    protected function write(int $id, string $bytes): void
    {
        $socket = $this->clients[$id]['socket'] ?? null;
        if (!$socket) {
            return;
        }
        stream_set_blocking($socket, true);
        $ok = @fwrite($socket, $bytes) !== false;
        stream_set_blocking($socket, false);
        if (!$ok) {
            $this->disconnect($id);
        }
    }

    protected function disconnect(int $id): void
    {
        if (isset($this->clients[$id])) {
            @fclose($this->clients[$id]['socket']);
            unset($this->clients[$id]);
        }
    }
}
