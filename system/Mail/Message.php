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

    public function toArray(): array
    {
        return [
            'to' => $this->to, 'cc' => $this->cc, 'bcc' => $this->bcc, 'from' => $this->from,
            'reply_to' => $this->replyTo, 'subject' => $this->subject, 'html' => $this->html, 'text' => $this->text,
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
