<?php
namespace System\Log;

use Throwable;

/**
 * Bongo's built-in logger.
 *
 *   logger()->info('Order {id} shipped', ['id' => 42]);
 *   Log::error('Payment failed', ['exception' => $e]);
 *
 * Line format:
 *   [2026-10-02 14:03:11] local.WARNING: Failed login {"email":"a@b.co","ip":"127.0.0.1"}
 *
 * Values under keys that look secret (password, token, secret, key) are masked.
 * Logging never throws: if the file cannot be written, the message goes to PHP's error log.
 */
class Logger
{
    public const LEVELS = [
        'debug'     => 100,
        'info'      => 200,
        'notice'    => 250,
        'warning'   => 300,
        'error'     => 400,
        'critical'  => 500,
        'alert'     => 550,
        'emergency' => 600,
    ];

    protected const SECRET_KEYS = ['password', 'pass', 'token', '_token', 'secret', 'key', 'app_key', 'authorization', 'cookie'];

    protected static ?Logger $instance = null;
    protected bool $pruned = false;

    public function __construct(
        protected string $path,
        protected string $channel = 'daily',
        protected string $minLevel = 'debug',
        protected int $days = 14,
        protected string $environment = 'local',
        protected string $format = 'text'
    ) {}

    public static function instance(): self
    {
        return self::$instance ??= new self(
            (string) config('logging.path', BASE_PATH . '/storage/logs'),
            (string) config('logging.channel', 'daily'),
            (string) config('logging.level', 'debug'),
            (int) config('logging.days', 14),
            (string) config('app.env', 'local'),
            (string) config('logging.format', env('LOG_FORMAT', 'text'))
        );
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function setFormat(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    /** Replace the shared logger (used by tests) */
    public static function setInstance(?self $logger): void
    {
        self::$instance = $logger;
    }

    public function debug(string $message, array $context = []): void     { $this->log('debug', $message, $context); }
    public function info(string $message, array $context = []): void      { $this->log('info', $message, $context); }
    public function notice(string $message, array $context = []): void    { $this->log('notice', $message, $context); }
    public function warning(string $message, array $context = []): void   { $this->log('warning', $message, $context); }
    public function error(string $message, array $context = []): void     { $this->log('error', $message, $context); }
    public function critical(string $message, array $context = []): void  { $this->log('critical', $message, $context); }
    public function alert(string $message, array $context = []): void     { $this->log('alert', $message, $context); }
    public function emergency(string $message, array $context = []): void { $this->log('emergency', $message, $context); }

    public function log(string $level, string $message, array $context = []): void
    {
        $level = strtolower($level);
        if (!isset(self::LEVELS[$level])) {
            $level = 'info';
        }

        if ($this->channel === 'none' || self::LEVELS[$level] < (self::LEVELS[strtolower($this->minLevel)] ?? 100)) {
            return;
        }

        $line = $this->format($level, $message, $context);

        try {
            if (!is_dir($this->path) && !@mkdir($this->path, 0775, true) && !is_dir($this->path)) {
                throw new \RuntimeException("Cannot create log directory {$this->path}");
            }
            if (@file_put_contents($this->file(), $line, FILE_APPEND | LOCK_EX) === false) {
                throw new \RuntimeException('Cannot write to ' . $this->file());
            }
            $this->prune();
        } catch (Throwable $e) {
            error_log('[Bongo logger] ' . $e->getMessage() . ' | ' . rtrim($line));
        }
    }

    /** Full path of the file the next line goes to */
    public function file(): string
    {
        return $this->channel === 'single'
            ? $this->path . '/bongo.log'
            : $this->path . '/bongo-' . date('Y-m-d') . '.log';
    }

    /**
     * Newest entries from the current log file, newest first.
     * Each entry: ['time', 'env', 'level', 'message', 'details' (trace lines)].
     * $minLevel filters out lower levels.
     */
    public function recent(int $limit = 50, ?string $minLevel = null): array
    {
        $file = $this->file();
        if (!is_file($file)) {
            return [];
        }

        // Read only the end of big files
        $size = filesize($file);
        $handle = fopen($file, 'r');
        fseek($handle, max(0, $size - 512 * 1024));
        $text = stream_get_contents($handle);
        fclose($handle);

        $min = $minLevel ? (self::LEVELS[strtolower($minLevel)] ?? 0) : 0;
        $entries = [];
        $current = null;

        foreach (preg_split('/\r?\n/', $text) as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') {
                continue;
            }
            if (str_starts_with($trimmed, '{') && ($json = json_decode($trimmed, true)) && isset($json['level'], $json['message'])) {
                if ($current) $entries[] = $current;
                $details = '';
                if (!empty($json['exception'])) {
                    if (is_array($json['exception'])) {
                        $details = ($json['exception']['class'] ?? 'Exception') . ': ' . ($json['exception']['message'] ?? '') . "\n";
                        if (!empty($json['exception']['trace'])) {
                            $details .= is_array($json['exception']['trace']) ? implode("\n", $json['exception']['trace']) : (string) $json['exception']['trace'];
                        }
                    } else {
                        $details = (string) $json['exception'];
                    }
                } elseif (!empty($json['context'])) {
                    $details = json_encode($json['context'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
                $current = [
                    'time' => $json['timestamp'] ?? date('Y-m-d H:i:s'),
                    'env' => $json['env'] ?? $this->environment,
                    'level' => strtolower($json['level']),
                    'message' => $json['message'],
                    'details' => $details,
                ];
            } elseif (preg_match('/^\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] (\w+)\.([A-Z]+): (.*)$/', $line, $m)) {
                if ($current) $entries[] = $current;
                $current = ['time' => $m[1], 'env' => $m[2], 'level' => strtolower($m[3]), 'message' => $m[4], 'details' => ''];
            } elseif ($current && $line !== '') {
                $current['details'] .= $line . "\n";
            }
        }
        if ($current) $entries[] = $current;

        $entries = array_filter($entries, fn($e) => (self::LEVELS[$e['level']] ?? 0) >= $min);

        return array_slice(array_reverse(array_values($entries)), 0, $limit);
    }

    protected function format(string $level, string $message, array $context): string
    {
        $exception = $context['exception'] ?? null;
        unset($context['exception']);

        $context = $this->mask($context);

        // {placeholder} interpolation from context
        $message = preg_replace_callback('/\{([\w.]+)\}/', function ($m) use ($context) {
            return array_key_exists($m[1], $context) ? $this->stringify($context[$m[1]]) : $m[0];
        }, $message);

        if ($this->format === 'json') {
            $payload = [
                'timestamp' => date('Y-m-d H:i:s'),
                'env'       => $this->environment,
                'level'     => strtolower($level),
                'message'   => $message,
            ];

            if (!empty($context)) {
                $payload['context'] = $context;
            }

            if ($exception instanceof Throwable) {
                $payload['exception'] = [
                    'class'   => get_class($exception),
                    'message' => $exception->getMessage(),
                    'code'    => $exception->getCode(),
                    'file'    => $exception->getFile(),
                    'line'    => $exception->getLine(),
                    'trace'   => explode("\n", $exception->getTraceAsString()),
                ];
            }

            return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR) . "\n";
        }

        // Entries start at the beginning of a line with "[date]": indent a message's extra lines,
        // so text such as "x\n[2026-01-01 ...] production.EMERGENCY: fake" can't fake an entry
        $message = str_replace(["\r\n", "\r", "\n"], "\n    ", $message);
        $line = sprintf('[%s] %s.%s: %s', date('Y-m-d H:i:s'), $this->environment, strtoupper($level), $message);

        if ($context) {
            $line .= ' ' . json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        }

        if ($exception instanceof Throwable) {
            $line .= "\n" . get_class($exception) . ': ' . $exception->getMessage()
                . ' in ' . $exception->getFile() . ':' . $exception->getLine()
                . "\n" . $exception->getTraceAsString();
        }

        return $line . "\n";
    }

    /** password, new_password, api_key, access_token, client_secret, X-Api-Key, Authorization, ... */
    protected static function isSecretKey(string $key): bool
    {
        $key = strtolower($key);
        return in_array($key, self::SECRET_KEYS, true)
            || (bool) preg_match('/pass(word|wd|phrase)?$|password|secret|token|api[_-]?key|private[_-]?key|app[_-]?key|authorization|cookie|credential/', $key);
    }

    protected function mask(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && self::isSecretKey($key)) {
                $context[$key] = '********';
            } elseif (is_array($value)) {
                $context[$key] = $this->mask($value);
            } elseif ($value instanceof Throwable) {
                $context[$key] = get_class($value) . ': ' . $value->getMessage();
            } elseif (is_object($value) && !$value instanceof \JsonSerializable) {
                $context[$key] = method_exists($value, '__toString') ? (string) $value : get_class($value);
            }
        }
        return $context;
    }

    protected function stringify($value): string
    {
        return is_scalar($value) || $value === null ? (string) $value : json_encode($value);
    }

    /** Delete daily files older than $days (once per request) */
    protected function prune(): void
    {
        if ($this->pruned || $this->channel !== 'daily' || $this->days <= 0) {
            return;
        }
        $this->pruned = true;

        $cutoff = date('Y-m-d', strtotime("-{$this->days} days"));
        foreach (glob($this->path . '/bongo-*.log') ?: [] as $file) {
            if (preg_match('/bongo-(\d{4}-\d{2}-\d{2})\.log$/', $file, $m) && $m[1] < $cutoff) {
                @unlink($file);
            }
        }
    }
}
