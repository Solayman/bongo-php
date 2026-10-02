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

    public static function to(string $email, ?string $name = null): Message
    {
        return (new Message(self::mailer()))->to($email, $name);
    }

    public static function message(): Message
    {
        return new Message(self::mailer());
    }
}
