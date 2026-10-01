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

        $files = array_merge(glob("$testsPath/Unit/*Test.php") ?: [], glob("$testsPath/Feature/*Test.php") ?: []);
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
                if (!str_starts_with($method, 'test')) continue;
                $name = "$short::$method";
                if ($filter && stripos($name, $filter) === false) continue;

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
        $testDb = env('DB_TEST_DATABASE', $mainDb . '_test');

        if ($testDb === $mainDb) {
            throw new \RuntimeException("The test database must not be the main database [$mainDb]. Set DB_TEST_DATABASE.");
        }

        $overrides = [
            'APP_ENV'     => 'testing',
            'APP_DEBUG'   => 'true',
            'DB_DATABASE' => $testDb,
            'APP_KEY'     => \System\Security\Key::generate(),
            'LOG_PATH'    => BASE_PATH . '/storage/logs/testing',
            'CACHE_PATH'  => BASE_PATH . '/storage/cache/testing',
            'MAIL_DRIVER' => 'file',
            'MAIL_PATH'   => BASE_PATH . '/storage/mail/testing',
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
        \System\Cache\Cache::setStore(null);
        (new \System\Cache\FileStore(BASE_PATH . '/storage/cache/testing'))->flush();
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

        // Create the test database if needed
        $pdo = new PDO("mysql:host={$db['host']};port={$db['port']};charset={$db['charset']}", $db['username'], $db['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        ]);
        $pdo->exec('CREATE DATABASE IF NOT EXISTS `' . str_replace('`', '``', $db['database']) . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');

        echo "Test database: {$db['database']}\n";

        $migrator = new Migrator(base_path('database/migrations'));
        $migrator->dropAllTables();
        $migrator->migrate();

        require_once base_path('database/seeders/DatabaseSeeder.php');
        (new \Database\Seeders\DatabaseSeeder())->run();
    }

    protected static function startServer(): void
    {
        // Find a free port
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr(stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);

        self::$baseUrl = "http://127.0.0.1:$port";

        $env = getenv() + [];
        foreach (['APP_ENV', 'APP_DEBUG', 'DB_DATABASE', 'APP_KEY', 'LOG_PATH', 'CACHE_PATH', 'MAIL_DRIVER', 'MAIL_PATH'] as $name) {
            $env[$name] = getenv($name);
        }
        $env['APP_URL'] = self::$baseUrl;

        $cmd = [PHP_BINARY, '-S', "127.0.0.1:$port", '-t', base_path('public')];
        $null = DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
        self::$server = proc_open($cmd, [0 => ['pipe', 'r'], 1 => ['file', $null, 'w'], 2 => ['file', $null, 'w']], $pipes, BASE_PATH, $env);

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
