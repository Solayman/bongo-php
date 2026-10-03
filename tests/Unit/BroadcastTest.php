<?php
namespace Tests\Unit;

use System\Broadcasting\Broadcast;
use System\Broadcasting\Frame;
use System\Testing\TestCase;

/**
 * Starts `php bongo websocket:serve` on a free port, connects like a browser,
 * and checks that Broadcast::to()->send() arrives.
 */
class BroadcastTest extends TestCase
{
    /** @var resource|null */
    protected $server = null;
    protected int $port = 0;

    public function setUp(): void
    {
        parent::setUp();
        Broadcast::reset();
    }

    public function tearDown(): void
    {
        if ($this->server) {
            proc_terminate($this->server);
            proc_close($this->server);
            $this->server = null;
        }
        $GLOBALS['config']['broadcasting'] = require BASE_PATH . '/config/broadcasting.php';
        Broadcast::reset();
    }

    protected function startServer(): void
    {
        $probe = stream_socket_server('tcp://127.0.0.1:0');
        $this->port = (int) substr(strrchr(stream_socket_get_name($probe, false), ':'), 1);
        fclose($probe);

        $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        $this->server = proc_open(
            [PHP_BINARY, BASE_PATH . '/bongo', 'websocket:serve', "--port={$this->port}"],
            [0 => ['pipe', 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']],
            $pipes, BASE_PATH, getenv() + ['BROADCAST_ALLOWED_ORIGINS' => 'http://good.test']
        );
        $GLOBALS['config']['broadcasting']['host'] = '127.0.0.1';
        $GLOBALS['config']['broadcasting']['port'] = $this->port;

        for ($i = 0; $i < 50; $i++) {
            if ($socket = @stream_socket_client("tcp://127.0.0.1:{$this->port}", $errno, $error, 0.2)) {
                fclose($socket);
                return;
            }
            usleep(100000);
        }
        throw new \RuntimeException('websocket:serve did not start');
    }

    /** @return resource a connected WebSocket, or the raw HTTP response when refused */
    protected function connect(string $origin = 'http://good.test')
    {
        $socket = stream_socket_client("tcp://127.0.0.1:{$this->port}", $errno, $error, 2);
        stream_set_timeout($socket, 3);
        $key = base64_encode(random_bytes(16));
        fwrite($socket, "GET / HTTP/1.1\r\nHost: 127.0.0.1\r\nUpgrade: websocket\r\nConnection: Upgrade\r\n"
            . "Sec-WebSocket-Key: $key\r\nSec-WebSocket-Version: 13\r\nOrigin: $origin\r\n\r\n");
        $response = '';
        while (!str_contains($response, "\r\n\r\n") && !feof($socket)) {
            $response .= fread($socket, 1);
        }
        if (!str_contains($response, ' 101 ')) {
            return $response;
        }
        $this->assertContains(base64_encode(sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true)), $response);
        return $socket;
    }

    protected function sendJson($socket, array $data): void
    {
        fwrite($socket, Frame::encode(json_encode($data), Frame::TEXT, true));
    }

    protected function readJson($socket): ?array
    {
        $buffer = '';
        while (($frame = Frame::decode($buffer)) === null) {
            $chunk = fread($socket, 8192);
            if ($chunk === '' || $chunk === false) {
                if (feof($socket) || stream_get_meta_data($socket)['timed_out']) return null;
                continue;
            }
            $buffer .= $chunk;
        }
        return json_decode($frame[2], true);
    }

    public function testFramesRoundTrip(): void
    {
        foreach ([0, 125, 126, 70000] as $length) {
            $payload = str_repeat('a', $length);
            $buffer = Frame::encode($payload, Frame::TEXT, true) . 'next';
            [$fin, $opcode, $decoded] = Frame::decode($buffer, 100000);
            $this->assertTrue($fin);
            $this->assertSame(Frame::TEXT, $opcode);
            $this->assertSame($payload, $decoded, "payload of $length bytes");
            $this->assertSame('next', $buffer);
        }
        $partial = substr(Frame::encode('hello'), 0, 3);
        $this->assertNull(Frame::decode($partial));
        $big = Frame::encode(str_repeat('x', 2000));
        $this->assertThrows(\LengthException::class, fn() => Frame::decode($big, 1000));
    }

    public function testPrivateChannelTokens(): void
    {
        $token = Broadcast::token('private-user.5');
        $this->assertTrue(Broadcast::verifyToken('private-user.5', $token));
        $this->assertFalse(Broadcast::verifyToken('private-user.6', $token), 'a token is for one channel only');
        $this->assertFalse(Broadcast::verifyToken('private-user.5', 'abc.def'));
        $this->assertFalse(Broadcast::verifyToken('private-user.5', Broadcast::token('private-user.5', -10)), 'expired');
    }

    public function testFakeRecordsBroadcasts(): void
    {
        Broadcast::fake();
        broadcast('orders', 'order.created', ['id' => 9]);
        $this->assertSame([['channel' => 'orders', 'event' => 'order.created', 'data' => ['id' => 9]]], Broadcast::sent());
    }

    public function testBroadcastReachesSubscribedBrowsers(): void
    {
        $this->startServer();

        $alice = $this->connect();
        $this->sendJson($alice, ['subscribe' => 'orders']);
        $this->assertSame(['subscribed' => 'orders'], $this->readJson($alice));

        $bob = $this->connect();
        $this->sendJson($bob, ['subscribe' => 'products']);
        $this->readJson($bob);

        $delivered = Broadcast::to('orders')->send('order.created', ['id' => 42, 'name' => 'চা']);
        $this->assertSame(1, $delivered, 'only the subscriber of "orders" gets it');
        $this->assertSame(['channel' => 'orders', 'event' => 'order.created', 'data' => ['id' => 42, 'name' => 'চা']], $this->readJson($alice));

        // Private channels need a valid token
        $this->sendJson($bob, ['subscribe' => 'private-user.5', 'token' => 'forged']);
        $this->assertContains('Not allowed', (string) ($this->readJson($bob)['error'] ?? ''));
        $this->sendJson($bob, ['subscribe' => 'private-user.5', 'token' => channel_token('private-user.5')]);
        $this->assertSame(['subscribed' => 'private-user.5'], $this->readJson($bob));
        $this->assertSame(1, Broadcast::to('private-user.5')->send('notice', ['text' => 'Paid']));
        $this->assertSame('Paid', $this->readJson($bob)['data']['text']);

        fclose($alice);
        fclose($bob);
    }

    public function testServerRejectsForgedBroadcastsAndForeignPages(): void
    {
        $this->startServer();

        $this->assertContains('403', $this->connect('http://evil.test'), 'pages from other sites cannot connect');

        $payload = json_encode(['channels' => ['orders'], 'event' => 'x', 'data' => [], 'time' => time()]);
        $body = json_encode(['payload' => $payload, 'signature' => str_repeat('0', 64)]);
        $socket = stream_socket_client("tcp://127.0.0.1:{$this->port}");
        fwrite($socket, "POST /broadcast HTTP/1.1\r\nHost: x\r\nContent-Type: application/json\r\nContent-Length: " . strlen($body) . "\r\n\r\n$body");
        $this->assertContains('403', (string) stream_get_contents($socket));
    }

    public function testDownServerNeverBreaksTheRequest(): void
    {
        $GLOBALS['config']['broadcasting']['port'] = 1;   // nothing listens there
        $this->assertSame(0, Broadcast::to('orders')->send('order.created'));
    }
}
