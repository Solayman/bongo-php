<?php
namespace System\Log;

/**
 * Static shortcut to the shared Logger: Log::info('Saved', ['id' => 5]);
 *
 * @method static void debug(string $message, array $context = [])
 * @method static void info(string $message, array $context = [])
 * @method static void notice(string $message, array $context = [])
 * @method static void warning(string $message, array $context = [])
 * @method static void error(string $message, array $context = [])
 * @method static void critical(string $message, array $context = [])
 * @method static void alert(string $message, array $context = [])
 * @method static void emergency(string $message, array $context = [])
 */
class Log
{
    public static function __callStatic(string $method, array $arguments): void
    {
        Logger::instance()->log($method, ...$arguments);
    }
}
