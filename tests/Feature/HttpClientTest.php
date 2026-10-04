<?php

namespace Tests\Feature;

use System\Http\Client\ConnectionException;
use System\Http\Client\RequestException;
use System\Http\Http;
use System\Testing\HttpTestCase;
use System\Testing\Runner;

class HttpClientTest extends HttpTestCase
{
    public function tearDown(): void
    {
        Http::reset();
    }

    public function testRealRequestToTheTestServer(): void
    {
        $response = Http::get(Runner::$baseUrl . '/api/health');
        $this->assertTrue($response->ok());
        $this->assertSame('ok', $response->json('status'));
        $this->assertContains('application/json', (string) $response->header('Content-Type'));

        $missing = Http::get(Runner::$baseUrl . '/api/nothing-here');
        $this->assertSame(404, $missing->status());
        $this->assertTrue($missing->failed());
        $this->assertThrows(RequestException::class, fn() => $missing->throw());
    }

    public function testFakesRecordWhatWasSent(): void
    {
        Http::fake([
            'sms.example.com/send' => Http::response(['id' => 'msg-7', 'queued' => true], 201),
            'pay.example.com/*'    => fn($request) => Http::response(['amount' => $request['data']['amount'] * 2]),
        ]);

        $sms = Http::withToken('secret-key')->post('https://sms.example.com/send', ['to' => '+8801700000000', 'text' => 'Code 1234']);
        $this->assertSame(201, $sms->status());
        $this->assertSame('msg-7', $sms->json('id'));

        $this->assertSame(20, Http::asForm()->post('https://pay.example.com/charge', ['amount' => 10])->json('amount'));
        $this->assertSame(200, Http::get('https://other.example.com', ['q' => 'x'])->status(), 'unmatched: 200 {}');

        $sent = Http::sent();
        $this->assertCount(3, $sent);
        $this->assertSame('POST', $sent[0]['method']);
        $this->assertSame('Bearer secret-key', $sent[0]['headers']['Authorization']);
        $this->assertSame('{"to":"+8801700000000","text":"Code 1234"}', $sent[0]['body'], 'JSON by default');
        $this->assertSame('amount=10', $sent[1]['body'], 'asForm()');
        $this->assertSame('https://other.example.com?q=x', $sent[2]['url']);
    }

    public function testRetriesServerErrors(): void
    {
        $calls = 0;
        Http::fake(['flaky.example.com/*' => function () use (&$calls) {
            return ++$calls < 3 ? Http::response(['error' => 'busy'], 503) : Http::response(['ok' => true]);
        }]);
        $this->assertTrue(Http::retry(3)->get('https://flaky.example.com/x')->ok());
        $this->assertSame(3, $calls);

        $calls = 0;
        $this->assertSame(503, Http::retry(2)->get('https://flaky.example.com/x')->status(), 'gives up after 2 tries');
    }

    public function testConnectionErrorsAndBadUrls(): void
    {
        $this->assertThrows(ConnectionException::class, fn() => Http::timeout(2)->get('http://127.0.0.1:1/nothing'));
        $this->assertThrows(\RuntimeException::class, fn() => Http::get('file:///etc/passwd'));
    }
}
