<?php
namespace System\Mail;

/**
 * Mail::to('a@b.co')->subject('Hi')->html('<p>Hello</p>')->send();
 * Driver and settings: config/mail.php (MAIL_DRIVER=log|smtp|file).
 */
class Mail
{
    protected static ?Mailer $mailer = null;

    public static function mailer(): Mailer
    {
        return self::$mailer ??= Mailer::fromConfig();
    }

    public static function setMailer(?Mailer $mailer): void
    {
        self::$mailer = $mailer;
    }

    /** For tests: keep emails in memory instead of sending them (see Mail::sent()) */
    public static function fake(): FakeMailer
    {
        return self::$mailer = new FakeMailer();
    }

    /** The emails sent since Mail::fake(), oldest first ([] when not faked) */
    public static function sent(): array
    {
        return self::$mailer instanceof FakeMailer ? self::$mailer->sent : [];
    }

    public static function to(string $email, ?string $name = null): Message
    {
        return (new Message(self::mailer()))->to($email, $name);
    }

    public static function message(): Message
    {
        return new Message(self::mailer());
    }
}
