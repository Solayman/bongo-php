<?php
namespace System\Log;

use Throwable;

/**
 * BongoMVC's built-in logger.
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
        protected string $environment = 'local'
    ) {}

    public static function instance(): self
    {
        return self::$instance ??= new self(
            (string) config('logging.path', BASE_PATH . '/storage/logs'),
            (string) config('logging.channel', 'daily'),
            (string) config('logging.level', 'debug'),
            (int) config('logging.days', 14),
            (string) config('app.env', 'local')
        );
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
            error_log('[BongoMVC logger] ' . $e->getMessage() . ' | ' . rtrim($line));
        }
    }

    /** Full path of the file the next line goes to */
    public function file(): string
    {
        return $this->channel === 'single'
            ? $this->path . '/bongo.log'
            : $this->path . '/bongo-' . date('Y-m-d') . '.log';
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

    protected function mask(array $context): array
    {
        foreach ($context as $key => $value) {
            if (is_string($key) && in_array(strtolower($key), self::SECRET_KEYS, true)) {
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
