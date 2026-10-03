<?php
namespace System\Database;

use PDO;
use PDOException;
use RuntimeException;

class Connection
{
    private static ?PDO $pdo = null;

    public static function getInstance(): PDO
    {
        if (self::$pdo) {
            return self::$pdo;
        }

        $config = config('database');
        $db = $config['connections'][$config['default']];

        try {
            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";
            self::$pdo = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
            // Same clock as PHP, so CURRENT_TIMESTAMP matches date() and log times
            self::$pdo->exec("SET time_zone = '" . date('P') . "'");
        } catch (PDOException $e) {
            \System\Log\Log::critical('Database connection failed: ' . $e->getMessage(), ['host' => $db['host'], 'database' => $db['database']]);
            // Throw instead of die() so callers can catch it and show a friendly error
            throw new RuntimeException('Database Connection Failed: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }
}
