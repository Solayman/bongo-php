<?php

namespace System\Testing;

use PDO;
use System\Database\Connection;
use System\Database\Migrator;

/**
 * Zero-dependency test runner used by `php bongo test [filter]`.
 *
 * - Uses a separate database (DB_TEST_DATABASE, default "<DB_DATABASE>_test"),
 *   rebuilt and seeded on every run. It refuses to run against the main database.
 * - Uses a temporary random APP_KEY, so the real key is never needed or touched.
 * - Feature tests (HttpTestCase) hit a real `php -S` server started on a free port.
 */
class Runner
{
    public static string $baseUrl = '';
    protected static $server = null;

    public static function run(string $testsPath, ?string $filter = null): int
    {
        self::prepareEnvironment();
        self::prepareDatabase();

        $files = array_merge(
            glob("$testsPath/*Test.php") ?: [],
            glob("$testsPath/Unit/*Test.php") ?: [],
            glob("$testsPath/Feature/*Test.php") ?: []
        );
        $before = get_declared_classes();
        foreach ($files as $file) {
            require_once $file;
        }
        $classes = array_filter(
            array_diff(get_declared_classes(), $before),
            fn($c) => is_subclass_of($c, TestCase::class) && !(new \ReflectionClass($c))->isAbstract()
        );

        $needsServer = (bool) array_filter($classes, fn($c) => is_subclass_of($c, HttpTestCase::class));
        if ($needsServer) {
            self::startServer();
        }

        $passed = $failed = $assertions = 0;
        $failures = [];
        $started = microtime(true);

        foreach ($classes as $class) {
            $short = (new \ReflectionClass($class))->getShortName();
            foreach (get_class_methods($class) as $method) {
                if (!str_starts_with($method, 'test')) {
                    continue;
                }
                $name = "$short::$method";
                if ($filter && stripos($name, $filter) === false) {
                    continue;
                }

                $test = new $class();
                try {
                    $test->setUp();
                    $test->$method();
                    $test->tearDown();
                    $passed++;
                    echo "  \033[32mPASS\033[0m $name\n";
                } catch (\Throwable $e) {
                    $failed++;
                    $failures[] = [$name, $e];
                    echo "  \033[31mFAIL\033[0m $name\n";
                }
                $assertions += $test->assertions;
            }
        }

        self::stopServer();
        // Leave the test cache empty: rate-limit counters and their lock files are only for this run
        (new \System\Cache\FileStore(BASE_PATH . '/storage/cache/testing'))->flush();

        foreach ($failures as [$name, $e]) {
            echo "\n\033[31m✗ $name\033[0m\n  " . get_class($e) . ': ' . $e->getMessage() . "\n";
            foreach (array_slice($e->getTrace(), 0, 3) as $frame) {
                if (isset($frame['file'])) {
                    echo '    at ' . str_replace(BASE_PATH . DIRECTORY_SEPARATOR, '', $frame['file']) . ':' . $frame['line'] . "\n";
                }
            }
        }

        $time = round(microtime(true) - $started, 2);
        echo "\nTests: " . ($failed ? "\033[31m$failed failed\033[0m, " : '') . "\033[32m$passed passed\033[0m ($assertions assertions) in {$time}s\n";

        return $failed ? 1 : 0;
    }

    protected static function prepareEnvironment(): void
    {
        $default = config('database.default');
        $mainDb = config("database.connections.$default.database");
        $sqlite = config("database.connections.$default.driver") === 'sqlite';
        $testDb = env('DB_TEST_DATABASE', $sqlite ? BASE_PATH . '/storage/testing/database.sqlite' : $mainDb . '_test');

        if ($testDb === $mainDb) {
            throw new \RuntimeException("The test database must not be the main database [$mainDb]. Set DB_TEST_DATABASE.");
        }

        $overrides = [
            'APP_ENV'     => 'testing',
            'APP_DEBUG'   => 'true',
            ($sqlite ? 'DB_SQLITE_PATH' : 'DB_DATABASE') => $testDb,
            'APP_KEY'     => \System\Security\Key::generate(),
            'LOG_PATH'    => BASE_PATH . '/storage/logs/testing',
            'CACHE_PATH'  => BASE_PATH . '/storage/cache/testing',
            'MAIL_DRIVER' => 'file',
            'MAIL_PATH'   => BASE_PATH . '/storage/mail/testing',
            'SECURITY_CSP' => "default-src 'self'; frame-ancestors 'self'",
            // Redis cache/sessions of a test run never mix with the app's keys
            'REDIS_PREFIX' => config('database.redis.prefix', 'bongo:') . 'testing:',
        ];
        foreach ($overrides as $name => $value) {
            $_ENV[$name] = $value;
            putenv("$name=$value");
        }

        $GLOBALS['config']['app']['env'] = 'testing';
        $GLOBALS['config']['logging']['path'] = BASE_PATH . '/storage/logs/testing';
        $GLOBALS['config']['cache']['path'] = BASE_PATH . '/storage/cache/testing';
        $GLOBALS['config']['mail']['driver'] = 'file';
        $GLOBALS['config']['mail']['file_path'] = BASE_PATH . '/storage/mail/testing';
        \System\Mail\Mail::setMailer(null);
        foreach (glob(BASE_PATH . '/storage/mail/testing/*.json') ?: [] as $old) {
            @unlink($old);
        }
        $GLOBALS['config']['database']['redis']['prefix'] = $overrides['REDIS_PREFIX'];
        \System\Cache\Cache::setStore(null);
        (new \System\Cache\FileStore(BASE_PATH . '/storage/cache/testing'))->flush();
        if (config('cache.driver') === 'redis') {
            \System\Cache\Cache::store()->flush();
        }
        \System\Log\Logger::setInstance(null);

        // Each run starts with an empty test log, so assertions only see this run's entries
        foreach (glob(BASE_PATH . '/storage/logs/testing/*.log') ?: [] as $old) {
            @unlink($old);
        }
        $GLOBALS['config']['database']['connections'][$default]['database'] = $testDb;
    }

    protected static function prepareDatabase(): void
    {
        $db = config('database.connections.' . config('database.default'));
        \System\Database\Connection::reset();

        // Create the test database if needed (a SQLite file is created on connect)
        if ($db['driver'] === 'mysql') {
            self::createMysqlDatabase($db);
        } elseif ($db['driver'] === 'pgsql') {
            self::createPgsqlDatabase($db);
        }

        echo "Test database: {$db['database']}\n";

        $paths = array_merge(
            [base_path('database/migrations')],
            class_exists(\System\Package\PackageManager::class) ? \System\Package\PackageManager::migrationPaths() : []
        );
        $migrator = new Migrator($paths);
        $migrator->dropAllTables();
        $migrator->migrate();

        require_once base_path('database/seeders/DatabaseSeeder.php');
        (new \Database\Seeders\DatabaseSeeder())->run();
    }

    protected static function createMysqlDatabase(array $db): void
    {
        $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};charset={$db['charset']}", $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $db['database']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }

    protected static function createPgsqlDatabase(array $db): void
    {
        // PostgreSQL has no CREATE DATABASE IF NOT EXISTS: connect to the "postgres" database and check first
        $pdo = new PDO("pgsql:host={$db['host']};port={$db['port']};dbname=postgres", $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $exists = $pdo->prepare('SELECT 1 FROM pg_database WHERE datname = ?');
        $exists->execute([$db['database']]);
        if (!$exists->fetchColumn()) {
            $pdo->exec('CREATE DATABASE "' . str_replace('"', '""', $db['database']) . '"');
        }
    }

    protected static function startServer(): void
    {
        // Find a free port
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        self::$baseUrl = "http://127.0.0.1:$port";

        $env = getenv() + [];
        foreach (['APP_ENV', 'APP_DEBUG', 'DB_DATABASE', 'APP_KEY', 'LOG_PATH', 'CACHE_PATH', 'MAIL_DRIVER', 'MAIL_PATH', 'SECURITY_CSP'] as $name) {
            $env[$name] = getenv($name);
        }
        foreach (['DB_CONNECTION', 'DB_SQLITE_PATH', 'DB_HOST', 'DB_PORT', 'DB_USERNAME', 'DB_PASSWORD', 'CACHE_DRIVER', 'SESSION_DRIVER', 'QUEUE_DRIVER', 'REDIS_HOST', 'REDIS_PORT', 'REDIS_PREFIX'] as $name) {
            if (getenv($name) !== false) {
                $env[$name] = getenv($name);
            }
        }
        $env['APP_URL'] = self::$baseUrl;

        $serverScript = base_path('server.php');
        $cmd = is_file($serverScript)
            ? [PHP_BINARY, '-S', "127.0.0.1:$port", $serverScript]
            : [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', base_path('public')];
        // The server needs the same database extension as this process (e.g. php -d extension=pdo_pgsql bongo test)
        $driver = config('database.connections.' . config('database.default') . '.driver');
        if (in_array($driver, ['sqlite', 'pgsql'], true) && extension_loaded("pdo_$driver")) {
            array_splice($cmd, 1, 0, ['-d', "extension=pdo_$driver"]);
        }
        $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        self::$server = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes, BASE_PATH, $env);

        // A fatal error or exit() inside a test ends this process early: never leave the server running
        register_shutdown_function(static function () {
            if (self::$server) {
                echo "\n\033[31mThe test run stopped early (a fatal error or exit() inside a test). The test server was stopped.\033[0m\n";
                self::stopServer();
                exit(1);   // an interrupted run never counts as passed
            }
        });

        for ($i = 0; $i < 50; $i++) {
            $conn = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
            if ($conn) {
                fclose($conn);
                echo "Test server: " . self::$baseUrl . "\n\n";
                return;
            }
            usleep(100000);
        }
        throw new \RuntimeException('Could not start the test server');
    }

    protected static function stopServer(): void
    {
        if (self::$server) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }
}
