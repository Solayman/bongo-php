<?php

namespace Tests\Unit\NotifyProbe {
    use System\Mail\Mail;
    use System\Mail\Message;
    use System\Notifications\Notification;

    class OrderShipped extends Notification
    {
        public function __construct(public string $number, public array $channels = ['mail', 'sms', 'slack'])
        {
        }
        public function via(object $user): array
        {
            return $this->channels;
        }
        public function toMail(object $user): Message
        {
            return Mail::message()->subject("Order {$this->number} shipped")->text('On its way!');
        }
        public function toSms(object $user): string
        {
            return "Order {$this->number} shipped";
        }
        public function toSlack(object $user): string
        {
            return "Shipped {$this->number} to {$user->name}";
        }
    }
}

namespace Tests\Unit {

    use System\Http\Http;
    use System\Mail\Mail;
    use System\Notifications\Notify;
    use System\Testing\TestCase;
    use Tests\Unit\NotifyProbe\OrderShipped;

    class NotificationTest extends TestCase
    {
        protected array $config;
        protected object $rahim;

        public function setUp(): void
        {
            $this->config = $GLOBALS['config']['notifications'] ?? [];
            $this->rahim = (object) ['name' => 'Rahim', 'email' => 'rahim@example.com', 'phone' => '+8801711111111'];
        }

        public function tearDown(): void
        {
            $GLOBALS['config']['notifications'] = $this->config;
            Mail::setMailer(null);
            Http::reset();
            Notify::reset();
        }

        public function testSendsThroughEveryChannel(): void
        {
            $GLOBALS['config']['notifications'] = [
                'sms'   => ['url' => 'https://sms.example.com/send', 'token' => 'k', 'fields' => ['to' => 'msisdn', 'text' => 'message'], 'extra' => ['sender_id' => 'Bongo']],
                'slack' => ['webhook' => 'https://hooks.slack.example.com/T1'],
            ];
            Mail::fake();
            Http::fake();

            Notify::send($this->rahim, new OrderShipped('ORD-42'));

            $this->assertMailSent('rahim@example.com', 'Order ORD-42 shipped');
            [$sms, $slack] = Http::sent();
            $this->assertSame('https://sms.example.com/send', $sms['url']);
            $this->assertSame(['msisdn' => '+8801711111111', 'message' => 'Order ORD-42 shipped', 'sender_id' => 'Bongo'], $sms['data']);
            $this->assertSame('Bearer k', $sms['headers']['Authorization']);
            $this->assertSame(['text' => 'Shipped ORD-42 to Rahim'], $slack['data']);
        }

        public function testWithoutGatewaysSmsAndSlackGoToTheLog(): void
        {
            $GLOBALS['config']['notifications'] = ['sms' => ['url' => ''], 'slack' => ['webhook' => '']];
            Http::fake();
            Notify::send([$this->rahim], new OrderShipped('ORD-43', ['sms', 'slack']));
            $this->assertSame([], Http::sent(), 'nothing went over HTTP');
        }

        public function testFakeRecordsAndMistakesAreClear(): void
        {
            Notify::fake();
            Notify::send($this->rahim, new OrderShipped('ORD-44', ['mail', 'sms']));
            $sent = Notify::sent();
            $this->assertSame(['mail', 'sms'], array_column($sent, 'channel'));
            $this->assertSame('Order ORD-44 shipped', $sent[1]['content']);
            Notify::reset();

            $noPhone = (object) ['name' => 'X', 'email' => 'x@example.com'];
            $this->assertThrows(\RuntimeException::class, fn() => Notify::send($noPhone, new OrderShipped('1', ['sms'])));
            $this->assertThrows(\RuntimeException::class, fn() => Notify::send($this->rahim, new OrderShipped('1', ['pigeon'])));
        }
    }
}
