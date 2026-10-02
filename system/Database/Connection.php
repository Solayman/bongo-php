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

        $config = require __DIR__ . '/../../config/database.php';
        $db = $config['connections'][$config['default']];

        try {
            $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";
            self::$pdo = new PDO($dsn, $db['username'], $db['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        } catch (PDOException $e) {
            // Throw instead of die() so callers can catch it and show a friendly error
            throw new RuntimeException('Database Connection Failed: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }
}
