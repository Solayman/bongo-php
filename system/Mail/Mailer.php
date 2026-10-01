<?php
namespace System\Mail;

use RuntimeException;
use System\Log\Log;

/**
 * Sends a Message with the configured driver:
 *   log   – writes the email to the application log (development default)
 *   file  – one JSON file per email in storage/mail (tests and previews)
 *   smtp  – a real SMTP server (SSL or STARTTLS, AUTH LOGIN)
 */
class Mailer
{
    public function __construct(protected array $config) {}

    public static function fromConfig(): self
    {
        return new self((array) config('mail', []));
    }

    public function send(Message $message): bool
    {
        if (!$message->to) {
            throw new RuntimeException('An email needs at least one recipient');
        }
        $message->from ??= [(string) ($this->config['from']['address'] ?? 'no-reply@localhost'), $this->config['from']['name'] ?? null];

        $driver = (string) ($this->config['driver'] ?? 'log');
        $ok = match ($driver) {
            'log'  => $this->sendLog($message),
            'file' => $this->sendFile($message),
            'smtp' => $this->sendSmtp($message),
            default => throw new RuntimeException("Unknown mail driver [$driver]"),
        };

        Log::info('Mail "{subject}" sent to {to} ({driver})', [
            'subject' => $message->subject,
            'to' => implode(', ', array_column($message->to, 0)),
            'driver' => $driver,
        ]);
        return $ok;
    }

    protected function sendLog(Message $message): bool
    {
        Log::debug("Mail body\n" . $this->build($message));
        return true;
    }

    protected function sendFile(Message $message): bool
    {
        $dir = (string) ($this->config['file_path'] ?? BASE_PATH . '/storage/mail');
        if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new RuntimeException("Cannot create mail directory $dir");
        }
        $name = date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.json';
        return file_put_contents("$dir/$name", json_encode($message->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false;
    }

    // ------------------------------------------------------------------
    // SMTP
    // ------------------------------------------------------------------

    protected function sendSmtp(Message $message): bool
    {
        $smtp = (array) ($this->config['smtp'] ?? []);
        $host = (string) ($smtp['host'] ?? 'localhost');
        $port = (int) ($smtp['port'] ?? 587);
        $encryption = strtolower((string) ($smtp['encryption'] ?? 'tls')); // tls (STARTTLS) | ssl | none
        $timeout = (int) ($smtp['timeout'] ?? 15);

        $target = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port";
        $socket = @stream_socket_client($target, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, stream_context_create(['ssl' => [
            'verify_peer' => (bool) ($smtp['verify_peer'] ?? true),
            'verify_peer_name' => (bool) ($smtp['verify_peer'] ?? true),
        ]]));
        if (!$socket) {
            throw new RuntimeException("SMTP connection to $host:$port failed: $errstr");
        }
        stream_set_timeout($socket, $timeout);

        try {
            $this->expect($socket, [220]);
            $hello = (string) ($smtp['helo'] ?? (gethostname() ?: 'localhost'));
            $this->command($socket, "EHLO $hello", [250]);

            if ($encryption === 'tls') {
                $this->command($socket, 'STARTTLS', [220]);
                if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                    throw new RuntimeException('SMTP STARTTLS failed');
                }
                $this->command($socket, "EHLO $hello", [250]);
            }

            if (!empty($smtp['username'])) {
                $this->command($socket, 'AUTH LOGIN', [334]);
                $this->command($socket, base64_encode((string) $smtp['username']), [334]);
                $this->command($socket, base64_encode((string) ($smtp['password'] ?? '')), [235], true);
            }

            $this->command($socket, 'MAIL FROM:<' . $message->from[0] . '>', [250]);
            foreach (array_merge($message->to, $message->cc, $message->bcc) as [$email]) {
                $this->command($socket, "RCPT TO:<$email>", [250, 251]);
            }
            $this->command($socket, 'DATA', [354]);

            // Dot-stuffing: a line starting with "." gets another "."
            $data = preg_replace('/^\./m', '..', $this->build($message));
            $this->command($socket, $data . "\r\n.", [250]);
            $this->command($socket, 'QUIT', [221]);
        } finally {
            fclose($socket);
        }
        return true;
    }

    protected function command($socket, string $line, array $expect, bool $secret = false): string
    {
        fwrite($socket, $line . "\r\n");
        try {
            return $this->expect($socket, $expect);
        } catch (RuntimeException $e) {
            throw new RuntimeException(($secret ? '[credentials]' : strtok($line, "\r\n")) . ' → ' . $e->getMessage());
        }
    }

    protected function expect($socket, array $codes): string
    {
        $response = '';
        while (($line = fgets($socket, 515)) !== false) {
            $response .= $line;
            if (strlen($line) < 4 || $line[3] === ' ') break; // last line of a multi-line reply
        }
        $code = (int) substr($response, 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new RuntimeException('SMTP error: ' . trim($response ?: 'no response'));
        }
        return $response;
    }

    // ------------------------------------------------------------------
    // MIME
    // ------------------------------------------------------------------

    /** The full message: headers + multipart/alternative (text + HTML) body */
    public function build(Message $message): string
    {
        $address = fn(array $a) => $a[1] ? '=?UTF-8?B?' . base64_encode($a[1]) . "?= <{$a[0]}>" : $a[0];
        $headers = [
            'Date: ' . date('r'),
            'From: ' . $address($message->from),
            'To: ' . implode(', ', array_map($address, $message->to)),
        ];
        if ($message->cc) $headers[] = 'Cc: ' . implode(', ', array_map($address, $message->cc));
        if ($message->replyTo) $headers[] = 'Reply-To: ' . $address($message->replyTo);
        $headers[] = 'Subject: =?UTF-8?B?' . base64_encode($message->subject) . '?=';
        $headers[] = 'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . (parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost') . '>';
        $headers[] = 'MIME-Version: 1.0';

        $text = $message->text ?? ($message->html ? Message::htmlToText($message->html) : '');
        $boundary = 'bongo_' . bin2hex(random_bytes(8));
        $headers[] = "Content-Type: multipart/alternative; boundary=\"$boundary\"";

        $body = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
            . chunk_split(base64_encode($text)) . "\r\n";
        if ($message->html !== null) {
            $body .= "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($message->html)) . "\r\n";
        }
        $body .= "--$boundary--";

        return implode("\r\n", $headers) . "\r\n\r\n" . $body;
    }
}
