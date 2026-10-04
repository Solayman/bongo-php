<?php

namespace Tests\Unit;

use System\Events\Event;
use System\Mail\Mailer;
use System\Mail\Message;
use System\Testing\TestCase;

class MailEventTest extends TestCase
{
    public function tearDown(): void
    {
        Event::forget();
    }

    public function testMailFakeAndAssertions(): void
    {
        \System\Mail\Mail::fake();
        try {
            $this->assertNoMailSent();
            \System\Mail\Mail::to('Rahim@Example.com', 'Rahim')->subject('Your order #42')->text('Thanks')->send();

            $mail = $this->assertMailSent('rahim@example.com', 'order #42');   // case-insensitive address
            $this->assertSame('Thanks', $mail['text']);
            $this->assertCount(1, \System\Mail\Mail::sent());

            // A missing email fails with a message that lists what was sent
            try {
                $this->assertMailSent('karim@example.com');
                throw new \RuntimeException('should have failed');
            } catch (\System\Testing\AssertionFailed $e) {
                $this->assertContains('No email to karim@example.com. Sent: "Your order #42" to Rahim@Example.com', $e->getMessage());
            }
        } finally {
            \System\Mail\Mail::setMailer(null);
        }
    }

    public function testAttachments(): void
    {
        $mailer = new Mailer(['driver' => 'log', 'from' => ['address' => 'shop@example.com', 'name' => 'Bongo Shop']]);
        $file = tempnam(sys_get_temp_dir(), 'inv');
        file_put_contents($file, "%PDF-1.4\nfake invoice");

        $message = (new \System\Mail\Message($mailer))->from('shop@example.com')->to('rahim@example.com')->subject('Invoice')->html('<p>Attached.</p>')
            ->attach($file, 'Invoice 42.pdf')
            ->attachData("id,total\n42,99.00\n", "orders\r\nBcc: evil@x.com.csv", 'text/csv');
        $raw = $mailer->build($message);
        @unlink($file);

        $this->assertContains('Content-Type: multipart/mixed;', $raw);
        $this->assertContains('Content-Type: multipart/alternative;', $raw, 'the text/HTML part is still there');
        $this->assertContains('Content-Type: application/pdf; name="=?UTF-8?B?' . base64_encode('Invoice 42.pdf') . '?="', $raw, 'type detected from the content');
        $this->assertContains(chunk_split(base64_encode("%PDF-1.4\nfake invoice")), $raw);
        $this->assertNotContains("\r\nBcc: evil", $raw, 'no header injection through a file name');
        $this->assertSame(2, count($message->attachments));

        // Queued mail keeps its attachments
        $rebuilt = new \System\Mail\SendQueuedMail($message->toArray());
        $this->assertSame('Invoice 42.pdf', $rebuilt->message['attachments'][0]['name']);

        $this->assertThrows(\InvalidArgumentException::class, fn() => (new \System\Mail\Message($mailer))->attach('/no/such/file.pdf'));
    }

    public function testMessageBuildsMimeAndBlocksHeaderInjection(): void
    {
        $mailer = new Mailer(['driver' => 'log', 'from' => ['address' => 'shop@example.com', 'name' => 'Bongo Shop']]);
        $message = (new Message($mailer))->to('rahim@example.com', 'রহিম')->subject("Your order\r\nBcc: evil@example.com")
            ->html('<p>Hello <a href="https://x.test/o/1">order</a></p>');
        $message->from = ['shop@example.com', 'Bongo Shop'];

        $this->assertSame('Your order  Bcc: evil@example.com', $message->subject, 'line breaks removed from the subject');
        $raw = $mailer->build($message);
        $this->assertNotContains("\r\nBcc:", $raw, 'no injected header');
        $this->assertContains('Subject: =?UTF-8?B?' . base64_encode($message->subject) . '?=', $raw);
        $this->assertContains('To: =?UTF-8?B?' . base64_encode('রহিম') . '?= <rahim@example.com>', $raw);
        $this->assertContains('multipart/alternative', $raw);
        $this->assertContains(chunk_split(base64_encode('Hello order (https://x.test/o/1)')), $raw, 'plain-text part made from the HTML');

        $this->assertThrows(\InvalidArgumentException::class, fn() => (new Message())->to("a@b.co\r\nBcc: x@y.z"));
        $this->assertThrows(\InvalidArgumentException::class, fn() => (new Message())->to('not-an-email'));
    }

    public function testFileDriverWritesJson(): void
    {
        $dir = sys_get_temp_dir() . '/bongo_mail_' . bin2hex(random_bytes(4));
        $mailer = new Mailer(['driver' => 'file', 'file_path' => $dir, 'from' => ['address' => 'shop@example.com']]);
        (new Message($mailer))->to('a@example.com')->subject('Hi')->html('<b>Hi</b>')->send();

        $files = glob("$dir/*.json");
        $this->assertCount(1, $files);
        $mail = json_decode(file_get_contents($files[0]), true);
        $this->assertSame('Hi', $mail['subject']);
        $this->assertSame([['a@example.com', null]], $mail['to']);
        $this->assertSame(['shop@example.com', null], $mail['from']);
        array_map('unlink', $files);
        rmdir($dir);
    }

    public function testSmtpConversationWithAFakeServer(): void
    {
        $dir = sys_get_temp_dir() . '/bongo_smtp_' . bin2hex(random_bytes(4));
        mkdir($dir);
        $port = $this->freePort();
        $server = $this->fakeSmtpServer($dir, $port);

        try {
            $mailer = new Mailer(['driver' => 'smtp', 'from' => ['address' => 'shop@example.com', 'name' => 'Shop'], 'smtp' => [
                'host' => '127.0.0.1', 'port' => $port, 'encryption' => 'none', 'username' => 'user', 'password' => 'secret', 'timeout' => 5,
            ]]);
            $ok = (new Message($mailer))->to('rahim@example.com')->cc('boss@example.com')->subject('Hello SMTP')
                ->text("Line one\n.starts with a dot")->send();
            $this->assertTrue($ok);

            for ($i = 0; $i < 50 && !is_file("$dir/session.txt"); $i++) {
                usleep(100000);
                clearstatcache();
            }
            $log = (string) @file_get_contents("$dir/session.txt");

            $this->assertContains('AUTH LOGIN', $log);
            $this->assertContains(base64_encode('user'), $log);
            $this->assertContains('MAIL FROM:<shop@example.com>', $log);
            $this->assertContains('RCPT TO:<rahim@example.com>', $log);
            $this->assertContains('RCPT TO:<boss@example.com>', $log, 'cc recipients are delivered too');
            $this->assertContains('Subject: =?UTF-8?B?' . base64_encode('Hello SMTP') . '?=', $log);
            $this->assertContains('QUIT', $log);
        } finally {
            proc_terminate($server);
            proc_close($server);
            @unlink("$dir/session.txt");
            @unlink("$dir/session.txt.part");
            @unlink("$dir/server.php");
            @rmdir($dir);
        }
    }

    public function testSmtpErrorsAreReported(): void
    {
        $mailer = new Mailer(['driver' => 'smtp', 'smtp' => ['host' => '127.0.0.1', 'port' => $this->freePort(), 'encryption' => 'none', 'timeout' => 2]]);
        $this->assertThrows(\RuntimeException::class, fn() => (new Message($mailer))->to('a@example.com')->subject('x')->text('x')->send());
    }

    public function testEventsWithClosuresClassesAndInjection(): void
    {
        $seen = [];
        Event::listen('order.shipped', function ($order) use (&$seen) {
            $seen[] = "closure:{$order['id']}";
        });
        Event::listen('order.shipped', function ($order, \System\Http\Request $request) use (&$seen) {
            $seen[] = 'injected:' . get_class($request);
        });
        $this->assertTrue(Event::hasListeners('order.shipped'));

        Event::dispatch('order.shipped', ['id' => 7]);
        $this->assertSame(['closure:7', 'injected:System\Http\Request'], $seen);

        // Object events: listeners are keyed by the class name and receive the object
        $event = new \ArrayObject(['total' => 99]);
        Event::listen(\ArrayObject::class, fn(\ArrayObject $e) => $e['total'] * 2);
        $this->assertSame([198], Event::dispatch($event));

        // A listener returning false stops the rest
        Event::listen('stop.test', fn() => false);
        Event::listen('stop.test', function () use (&$seen) {
            $seen[] = 'never';
        });
        Event::dispatch('stop.test');
        $this->assertNotContains('never', implode(',', $seen));
    }

    // ------------------------------------------------------------------

    protected function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        return $port;
    }

    /** An SMTP server (separate process) that logs what the client sends */
    protected function fakeSmtpServer(string $dir, int $port)
    {
        $crlf = '"\r\n"';
        $script = <<<PHP
<?php
[\$script, \$port, \$log] = \$argv;
\$server = stream_socket_server("tcp://127.0.0.1:\$port", \$errno, \$errstr);
\$out = fopen(\$log . '.part', 'w');
// Keep accepting: readiness probes connect and close at once; the real client sends EHLO
while (\$conn = @stream_socket_accept(\$server, 10)) {
    \$say = fn(\$line) => fwrite(\$conn, \$line . $crlf);
    \$say('220 fake.smtp ready');
    \$auth = 0; \$data = false; \$done = false;
    while ((\$line = fgets(\$conn)) !== false) {
        fwrite(\$out, \$line);
        \$cmd = rtrim(\$line, $crlf);
        if (\$data) {
            if (\$cmd === '.') { \$data = false; \$say('250 queued'); }
            continue;
        }
        if (str_starts_with(\$cmd, 'EHLO')) { \$say('250-fake.smtp'); \$say('250 AUTH LOGIN'); }
        elseif (\$cmd === 'AUTH LOGIN') { \$auth = 1; \$say('334 VXNlcm5hbWU6'); }
        elseif (\$auth === 1) { \$auth = 2; \$say('334 UGFzc3dvcmQ6'); }
        elseif (\$auth === 2) { \$auth = 3; \$say('235 ok'); }
        elseif (str_starts_with(\$cmd, 'MAIL FROM') || str_starts_with(\$cmd, 'RCPT TO')) { \$say('250 ok'); }
        elseif (\$cmd === 'DATA') { \$data = true; \$say('354 go ahead'); }
        elseif (\$cmd === 'QUIT') { \$say('221 bye'); \$done = true; break; }
        else { \$say('500 unknown'); }
    }
    fclose(\$conn);
    if (\$done) break;
}
fclose(\$out);
rename(\$log . '.part', \$log);
PHP;
        file_put_contents("$dir/server.php", $script);

        $process = proc_open([PHP_BINARY, "$dir/server.php", (string) $port, "$dir/session.txt"], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        for ($i = 0; $i < 50; $i++) {
            $probe = @fsockopen('127.0.0.1', $port, $e, $s, 0.1);
            if ($probe) {
                fclose($probe);
                return $process;
            }
            usleep(100000);
        }
        throw new \RuntimeException('Fake SMTP server did not start');
    }
}
