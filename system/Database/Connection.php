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
    /** @var array<string, PDO> Cached PDO connection instances by connection name */
    private static array $instances = [];
    /** @var array<string, string> Cached driver names by connection name */
    private static array $drivers = [];
    /** @var array<string, array> Runtime registered connection configurations */
    private static array $runtimeConfigs = [];
    private static ?string $defaultConnection = null;

    public static function getDefaultConnection(): string
    {
        return self::$defaultConnection ??= (string) (config('database.default') ?? 'mysql');
    }

    public static function setDefaultConnection(?string $name): void
    {
        self::$defaultConnection = $name;
    }

    public static function getInstance(?string $name = null): PDO
    {
        $name = $name ?? self::getDefaultConnection();

        if (isset(self::$instances[$name])) {
            return self::$instances[$name];
        }

        $db = self::config($name);
        $driver = self::driver($name);

        try {
            self::$instances[$name] = match ($driver) {
                'sqlite' => self::connectSqlite($db),
                'pgsql'  => self::connectPgsql($db),
                default  => self::connectMysql($db),
            };
        } catch (PDOException $e) {
            \System\Log\Log::critical('Database connection failed: ' . $e->getMessage(), [
                'connection' => $name,
                'driver'     => $driver,
                'database'   => $db['database'] ?? null,
            ]);
            throw new RuntimeException("Database Connection [$name] Failed: " . $e->getMessage(), 0, $e);
        }

        return self::$instances[$name];
    }

    /** "mysql", "sqlite", or "pgsql" for the given or default connection */
    public static function driver(?string $name = null): string
    {
        $name = $name ?? self::getDefaultConnection();
        return self::$drivers[$name] ??= (string) (self::config($name)['driver'] ?? 'mysql');
    }

    public static function isMysql(?string $name = null): bool
    {
        return self::driver($name) === 'mysql';
    }

    public static function isSqlite(?string $name = null): bool
    {
        return self::driver($name) === 'sqlite';
    }

    public static function isPgsql(?string $name = null): bool
    {
        return self::driver($name) === 'pgsql';
    }

    /** Manually set a mock or active PDO instance for a connection (useful in tests) */
    public static function setInstance(string $name, PDO $pdo, ?string $driver = null): void
    {
        self::$instances[$name] = $pdo;
        if ($driver !== null) {
            self::$drivers[$name] = $driver;
        }
    }

    /** Register a dynamic database connection configuration at runtime (e.g. for multi-tenancy) */
    public static function addConnection(string $name, array $config): void
    {
        self::$runtimeConfigs[$name] = $config;
        unset(self::$instances[$name], self::$drivers[$name]);
    }

    public static function hasConnection(string $name): bool
    {
        if (isset(self::$runtimeConfigs[$name])) {
            return true;
        }
        $all = config('database.connections');
        return is_array($all) && isset($all[$name]);
    }

    public static function removeConnection(string $name): void
    {
        self::reset($name);
        unset(self::$runtimeConfigs[$name]);
    }

    /** Forget one connection or all connections */
    public static function reset(?string $name = null): void
    {
        if ($name === null) {
            self::$instances = [];
            self::$drivers = [];
            self::$defaultConnection = null;
        } else {
            unset(self::$instances[$name], self::$drivers[$name]);
        }
        if (class_exists(DBConnection::class, false)) {
            DBConnection::reset($name);
        }
    }

    protected static function config(?string $name = null): array
    {
        $name = $name ?? self::getDefaultConnection();
        if (isset(self::$runtimeConfigs[$name])) {
            return self::$runtimeConfigs[$name];
        }
        $config = config('database');
        if (!isset($config['connections'][$name])) {
            throw new RuntimeException("Database connection [$name] is not configured in config/database.php.");
        }
        return $config['connections'][$name];
    }

    /** "persistent" (DB_PERSISTENT=true) reuses the server connection across requests, saving the connect time */
    protected static function options(array $db = []): array
    {
        return [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT => !empty($db['persistent']),
        ];
    }

    /** A reused connection must not carry an unfinished transaction from an earlier request */
    protected static function cleanPersistent(PDO $pdo, array $db): void
    {
        if (!empty($db['persistent']) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }

    protected static function syncTimezone(): void
    {
        $tz = (string) config('app.timezone', 'UTC');
        if ($tz !== '' && date_default_timezone_get() !== $tz) {
            @date_default_timezone_set($tz);
        }
    }

    protected static function connectMysql(array $db): PDO
    {
        $dsn = "mysql:host={$db['host']};port={$db['port']};dbname={$db['database']};charset={$db['charset']}";
        $pdo = new PDO($dsn, $db['username'], $db['password'], self::options($db));
        self::cleanPersistent($pdo, $db);
        self::syncTimezone();
        // Same clock as PHP, so CURRENT_TIMESTAMP matches date() and log times
        $pdo->exec("SET time_zone = '" . date('P') . "'");
        return $pdo;
    }

    protected static function connectSqlite(array $db): PDO
    {
        self::syncTimezone();
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

    protected static function connectPgsql(array $db): PDO
    {
        self::syncTimezone();
        if (!in_array('pgsql', PDO::getAvailableDrivers(), true)) {
            throw new PDOException('The pdo_pgsql PHP extension is not enabled. Add "extension=pdo_pgsql" to php.ini.');
        }
        $host = $db['host'] ?? '127.0.0.1';
        $port = (int) ($db['port'] ?? 5432);
        $database = $db['database'] ?? 'bongo';
        $sslmode = $db['sslmode'] ?? 'prefer';
        $dsn = "pgsql:host={$host};port={$port};dbname={$database};sslmode={$sslmode}";
        $pdo = new PDO($dsn, $db['username'] ?? 'postgres', $db['password'] ?? '', self::options($db));
        self::cleanPersistent($pdo, $db);
        if (!empty($db['schema'])) {
            $pdo->exec('SET search_path TO ' . $db['schema']);
        }
        $pdo->exec("SET timezone = '" . date('P') . "'");
        return $pdo;
    }
}
