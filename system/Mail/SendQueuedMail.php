<?php
namespace System\Mail;

use System\Queue\Job;

/**
 * Sends an email from the queue (created by Message::queue()).
 * Holds plain data only, so it serializes safely.
 */
class SendQueuedMail extends Job
{
    public int $tries = 3;
    public int|array $backoff = [30, 120, 600];

    public function __construct(public array $message) {}

    public function handle(): void
    {
        $m = new Message();
        foreach ($this->message['to'] as [$email, $name]) $m->to($email, $name);
        foreach ($this->message['cc'] as [$email, $name]) $m->cc($email, $name);
        foreach ($this->message['bcc'] as [$email, $name]) $m->bcc($email, $name);
        if ($this->message['from']) $m->from(...$this->message['from']);
        if ($this->message['reply_to']) $m->replyTo(...$this->message['reply_to']);
        $m->subject($this->message['subject']);
        if ($this->message['html'] !== null) $m->html($this->message['html']);
        if ($this->message['text'] !== null) $m->text($this->message['text']);
        foreach ($this->message['attachments'] ?? [] as $a) $m->attachData(base64_decode($a['content']), $a['name'], $a['mime']);
        $m->send();
    }

    public function displayName(): string
    {
        return 'Mail: ' . $this->message['subject'];
    }
}
