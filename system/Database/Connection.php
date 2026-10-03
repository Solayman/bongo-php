<?php
namespace System\Database;

use PDO;
use PDOException;
use RuntimeException;

/**
 * The one PDO connection. config/database.php picks the driver:
 *   mysql  - a MySQL / MariaDB server (default)
 *   sqlite - a single file, no server needed (DB_CONNECTION=sqlite)
 */
class Connection
{
    private static ?PDO $pdo = null;

    public static function getInstance(): PDO
    {
        if (self::$pdo) {
            return self::$pdo;
        }

        $db = self::config();

        try {
            self::$pdo = self::driver() === 'sqlite' ? self::connectSqlite($db) : self::connectMysql($db);
        } catch (PDOException $e) {
            \System\Log\Log::critical('Database connection failed: ' . $e->getMessage(), ['driver' => self::driver(), 'database' => $db['database'] ?? null]);
            // Throw instead of die() so callers can catch it and show a friendly error
            throw new RuntimeException('Database Connection Failed: ' . $e->getMessage(), 0, $e);
        }

        return self::$pdo;
    }

    /** "mysql" or "sqlite" */
    public static function driver(): string
    {
        return self::config()['driver'] ?? 'mysql';
    }

    public static function isSqlite(): bool
    {
        return self::driver() === 'sqlite';
    }

    /** Forget the connection, e.g. after changing config('database') */
    public static function reset(): void
    {
        self::$pdo = null;
    }

    protected static function config(): array
    {
        $config = config('database');
        return $config['connections'][$config['default']];
    }

    protected static function options(): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];
    }

    protected static function connectMysql(array $db): PDO
    {
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";
        $pdo = new PDO($dsn, $db['username'], $db['password'], self::options());
        // Same clock as PHP, so CURRENT_TIMESTAMP matches date() and log times
        $pdo->exec("SET time_zone = '" . date('P') . "'");
        return $pdo;
    }

    protected static function connectSqlite(array $db): PDO
    {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            throw new PDOException('the pdo_sqlite PHP extension is not enabled. Add "extension=pdo_sqlite" to php.ini.');
        }
        $path = (string) $db['database'];
        if ($path !== ':memory:' && !is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, self::options());
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA busy_timeout = 5000');
        return $pdo;
    }
}
