<?php

namespace System\Notifications;

use RuntimeException;
use System\Http\Http;
use System\Log\Log;
use System\Mail\Message;

/**
 * Notify::send($user, new OrderShipped($order));      // one person or a list of them
 *
 * Who receives what: mail → $user->email; sms → $user->phone; slack → config('notifications.slack.webhook').
 * SMS and Slack go through the HTTP client to the URLs in config/notifications.php, or to the log
 * when no URL is set (development). Notify::fake() / Notify::sent() for tests.
 */
class Notify
{
    protected static ?array $sent = null;

    public static function send(object|array $notifiables, Notification $notification): void
    {
        foreach (is_array($notifiables) ? $notifiables : [$notifiables] as $notifiable) {
            foreach ($notification->via($notifiable) as $channel) {
                $method = 'to' . ucfirst($channel);
                if (!method_exists($notification, $method)) {
                    throw new RuntimeException(get_class($notification) . " sends by $channel but has no $method() method");
                }
                $content = $notification->$method($notifiable);
                if (self::$sent !== null) {
                    self::$sent[] = ['channel' => $channel, 'to' => $notifiable, 'notification' => $notification, 'content' => $content];
                    continue;
                }
                match ($channel) {
                    'mail'  => self::mail($notifiable, $content),
                    'sms'   => self::sms($notifiable, (string) $content),
                    'slack' => self::slack((string) $content),
                    default => throw new RuntimeException("Unknown notification channel [$channel]: use mail, sms or slack"),
                };
            }
        }
    }

    protected static function mail(object $notifiable, Message $message): void
    {
        if (!$message->to) {
            if (empty($notifiable->email)) {
                throw new RuntimeException('Cannot email this notification: the recipient has no email');
            }
            $message->to((string) $notifiable->email, $notifiable->name ?? null);
        }
        $message->send();
    }

    protected static function sms(object $notifiable, string $text): void
    {
        $phone = (string) ($notifiable->phone ?? '');
        if ($phone === '') {
            throw new RuntimeException('Cannot send an SMS: the recipient has no phone number');
        }
        $url = (string) config('notifications.sms.url', '');
        if ($url === '') {
            Log::info('SMS to {phone}: {text}', ['phone' => $phone, 'text' => $text]);
            return;
        }
        // The gateway gets {to, text} (field names configurable) plus any fixed fields, e.g. an API key
        $fields = (array) config('notifications.sms.fields', ['to' => 'to', 'text' => 'text']);
        $data = [$fields['to'] ?? 'to' => $phone, $fields['text'] ?? 'text' => $text] + (array) config('notifications.sms.extra', []);
        $request = Http::timeout(15)->retry(2, 500);
        if ($token = (string) config('notifications.sms.token', '')) {
            $request->withToken($token);
        }
        $request->post($url, $data)->throw();
    }

    protected static function slack(string $text): void
    {
        $webhook = (string) config('notifications.slack.webhook', '');
        if ($webhook === '') {
            Log::info('Slack: {text}', ['text' => $text]);
            return;
        }
        Http::timeout(15)->retry(2, 500)->post($webhook, ['text' => $text])->throw();
    }

    /** For tests: record instead of sending (see sent()) */
    public static function fake(): void
    {
        self::$sent = [];
    }

    /** Recorded notifications: [channel, to, notification, content] */
    public static function sent(): array
    {
        return self::$sent ?? [];
    }

    public static function reset(): void
    {
        self::$sent = null;
    }
}
