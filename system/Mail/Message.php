<?php
namespace System\Mail;

/**
 * One email, built fluently:
 *   Mail::to('rahim@example.com', 'Rahim')->subject('Your order')->view('emails.order', ['order' => $o])->send();
 */
class Message
{
    public array $to = [];
    public array $cc = [];
    public array $bcc = [];
    public ?array $from = null;
    public ?array $replyTo = null;
    public string $subject = '';
    public ?string $html = null;
    public ?string $text = null;
    /** [name, mime, base64 content] */
    public array $attachments = [];

    public function __construct(protected ?Mailer $mailer = null) {}

    public function to(string $email, ?string $name = null): static
    {
        $this->to[] = [self::assertEmail($email), $name];
        return $this;
    }

    public function cc(string $email, ?string $name = null): static
    {
        $this->cc[] = [self::assertEmail($email), $name];
        return $this;
    }

    public function bcc(string $email, ?string $name = null): static
    {
        $this->bcc[] = [self::assertEmail($email), $name];
        return $this;
    }

    public function from(string $email, ?string $name = null): static
    {
        $this->from = [self::assertEmail($email), $name];
        return $this;
    }

    public function replyTo(string $email, ?string $name = null): static
    {
        $this->replyTo = [self::assertEmail($email), $name];
        return $this;
    }

    public function subject(string $subject): static
    {
        // No header injection through the subject
        $this->subject = trim(str_replace(["\r", "\n"], ' ', $subject));
        return $this;
    }

    public function html(string $html): static
    {
        $this->html = $html;
        return $this;
    }

    /** Attach a file: ->attach($path) or ->attach($path, 'Invoice 42.pdf') */
    public function attach(string $path, ?string $name = null, ?string $mime = null): static
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException("Cannot attach [$path]: the file does not exist or cannot be read");
        }
        return $this->attachData((string) file_get_contents($path), $name ?? basename($path), $mime);
    }

    /** Attach generated content: ->attachData($pdf, 'invoice.pdf') */
    public function attachData(string $content, string $name, ?string $mime = null): static
    {
        // The name goes into a MIME header: no line breaks or quotes
        $name = trim(str_replace(["\r", "\n", '"'], '', $name)) ?: 'attachment';
        if ($mime === null) {
            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : false;
            $mime = $finfo ? (string) finfo_buffer($finfo, $content) : 'application/octet-stream';
        }
        $this->attachments[] = ['name' => $name, 'mime' => $mime, 'content' => base64_encode($content)];
        return $this;
    }

    public function text(string $text): static
    {
        $this->text = $text;
        return $this;
    }

    /** Render a template as the HTML body; a plain-text copy is made from it */
    public function view(string $view, array $data = []): static
    {
        $this->html = view($view, $data);
        $this->text ??= self::htmlToText($this->html);
        return $this;
    }

    public function send(): bool
    {
        return ($this->mailer ?? Mail::mailer())->send($this);
    }

    /** Send later through the queue (right away with the "sync" queue driver) */
    public function queue(int $delaySeconds = 0): void
    {
        \System\Queue\Queue::push(new SendQueuedMail($this->toArray()), $delaySeconds);
    }

    public function toArray(): array
    {
        return [
            'to' => $this->to, 'cc' => $this->cc, 'bcc' => $this->bcc, 'from' => $this->from,
            'reply_to' => $this->replyTo, 'subject' => $this->subject, 'html' => $this->html, 'text' => $this->text,
            'attachments' => $this->attachments,
        ];
    }

    public static function htmlToText(string $html): string
    {
        $html = preg_replace('#<a\s[^>]*href="([^"]+)"[^>]*>(.*?)</a>#is', '$2 ($1)', $html);
        $html = preg_replace('#<(br|/p|/div|/h\d|/li|/tr)[^>]*>#i', "\n", $html);
        $text = html_entity_decode(strip_tags($html), ENT_QUOTES, 'UTF-8');
        return trim(preg_replace("/\n\s*\n\s*\n+/", "\n\n", preg_replace('/[ \t]+/', ' ', $text)));
    }

    protected static function assertEmail(string $email): string
    {
        $email = trim($email);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || preg_match('/[\r\n]/', $email)) {
            throw new \InvalidArgumentException("Invalid email address [$email]");
        }
        return $email;
    }
}
