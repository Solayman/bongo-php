<?php

namespace System\Database;

use PDO;
use System\Log\Log;
use RuntimeException;

/**
 * Runs database/migrations/*.php in filename order.
 * Every run is tracked in the `migrations` table (name + batch number),
 * so a migration only runs once and a whole batch can be rolled back.
 */
class Migrator
{
    public const TABLE = 'migrations';

    protected PDO $pdo;

    public function __construct(protected string|array $path, protected ?string $connection = null)
    {
        $this->pdo = Connection::getInstance($this->connection);
    }

    public function getConnectionName(): ?string
    {
        return $this->connection ?? Connection::getDefaultConnection();
    }

    public function ensureTable(): void
    {
        if (Connection::isSqlite($this->connection)) {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                migration TEXT NOT NULL UNIQUE,
                batch INTEGER NOT NULL,
                ran_at TEXT NOT NULL DEFAULT (' . Schema\Blueprint::sqliteNow() . ')
            )');
            return;
        }
        if (Connection::isPgsql($this->connection)) {
            $this->pdo->exec('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
                id SERIAL PRIMARY KEY,
                migration VARCHAR(255) NOT NULL UNIQUE,
                batch INT NOT NULL,
                ran_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )');
            return;
        }
        $this->pdo->exec('CREATE TABLE IF NOT EXISTS ' . self::TABLE . ' (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            migration VARCHAR(255) NOT NULL UNIQUE,
            batch INT UNSIGNED NOT NULL,
            ran_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB');
    }

    /** name => file path, sorted by name */
    public function files(): array
    {
        $paths = is_array($this->path) ? $this->path : [$this->path];
        $files = [];
        foreach ($paths as $dir) {
            foreach (glob(rtrim($dir, '/\\') . '/*.php') ?: [] as $file) {
                $files[basename($file, '.php')] = $file;
            }
        }
        ksort($files);
        return $files;
    }

    /** name => batch for every migration that has run */
    public function ran(): array
    {
        $this->ensureTable();
        return $this->pdo->query('SELECT migration, batch FROM ' . self::TABLE . ' ORDER BY id')
            ->fetchAll(PDO::FETCH_KEY_PAIR);
    }

    /**
     * Status of every migration: [name, ran (bool), batch|null, ran_at|null]
     */
    public function status(): array
    {
        $this->ensureTable();
        $rows = $this->pdo->query('SELECT migration, batch, ran_at FROM ' . self::TABLE)->fetchAll(PDO::FETCH_ASSOC);
        $ran = array_column($rows, null, 'migration');

        $names = array_unique(array_merge(array_keys($this->files()), array_keys($ran)));
        sort($names);

        return array_map(fn($name) => [
            'migration' => $name,
            'ran'       => isset($ran[$name]),
            'batch'     => $ran[$name]['batch'] ?? null,
            'ran_at'    => $ran[$name]['ran_at'] ?? null,
            'missing'   => !isset($this->files()[$name]),
        ], $names);
    }

    /**
     * Run pending migrations as one new batch; returns the names that ran
     */
    public function migrate(?callable $log = null): array
    {
        $ran = $this->ran();
        $batch = ($ran ? max($ran) : 0) + 1;

        return $this->withConnectionScope(function () use ($ran, $batch, $log) {
            $done = [];
            foreach ($this->files() as $name => $file) {
                if (isset($ran[$name])) {
                    continue;
                }

                $this->resolve($file)->up();

                $stmt = $this->pdo->prepare('INSERT INTO ' . self::TABLE . ' (migration, batch) VALUES (?, ?)');
                $stmt->execute([$name, $batch]);

                $done[] = $name;
                Log::info('Migrated {migration} (batch {batch})', ['migration' => $name, 'batch' => $batch, 'connection' => $this->connection]);
                if ($log) {
                    $log($name);
                }
            }
            return $done;
        });
    }

    /**
     * Undo the last batch (or the last $steps batches), newest migration first
     */
    public function rollback(int $steps = 1, ?callable $log = null): array
    {
        $this->ensureTable();
        $batches = $this->pdo->query('SELECT DISTINCT batch FROM ' . self::TABLE . ' ORDER BY batch DESC')
            ->fetchAll(PDO::FETCH_COLUMN);
        $batches = array_slice($batches, 0, max(1, $steps));
        if (!$batches) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($batches), '?'));
        $stmt = $this->pdo->prepare('SELECT migration FROM ' . self::TABLE . " WHERE batch IN ($placeholders) ORDER BY id DESC");
        $stmt->execute($batches);

        $files = $this->files();

        return $this->withConnectionScope(function () use ($stmt, $files, $log) {
            $done = [];
            foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $name) {
                if (!isset($files[$name])) {
                    throw new RuntimeException("Cannot roll back [$name]: its file is missing from database/migrations");
                }

                $this->resolve($files[$name])->down();
                $this->pdo->prepare('DELETE FROM ' . self::TABLE . ' WHERE migration = ?')->execute([$name]);
                Log::info('Rolled back {migration}', ['migration' => $name, 'connection' => $this->connection]);

                $done[] = $name;
                if ($log) {
                    $log($name);
                }
            }
            return $done;
        });
    }

    /** Run a callback scoped to this migrator's target connection */
    protected function withConnectionScope(callable $callback)
    {
        $prevDefault = Connection::getDefaultConnection();
        if ($this->connection !== null) {
            Connection::setDefaultConnection($this->connection);
        }

        try {
            return $callback();
        } finally {
            if ($this->connection !== null) {
                Connection::setDefaultConnection($prevDefault);
            }
        }
    }

    /**
     * Drop every table in the current database
     */
    public function dropAllTables(): array
    {
        $schema = Schema\Schema::connection($this->connection);
        $tables = $schema->tables();

        $schema->withoutForeignKeyChecks(function () use ($tables, $schema) {
            foreach ($tables as $table) {
                $schema->drop($table, cascade: true);
            }
        });

        Log::warning('Dropped all tables', ['tables' => $tables, 'connection' => $this->connection]);
        return $tables;
    }

    /**
     * Create a new migration file from a name like "create_posts_table"
     */
    public function make(string $name): string
    {
        if (!preg_match('/^[a-z0-9_]+$/', $name)) {
            throw new RuntimeException('Migration names may only contain a-z, 0-9 and _ (e.g. create_posts_table)');
        }

        $table = preg_match('/^create_(\w+)_table$/', $name, $m) ? $m[1] : null;
        // The prefix must sort after every existing migration, whatever the clock or timezone says
        $prefix = date('Y_m_d_His');
        $existing = array_keys($this->files());
        $last = $existing ? substr((string) end($existing), 0, 17) : '';
        if ($last !== '' && strcmp($prefix, $last) <= 0) {
            $prefix = substr($last, 0, 11) . str_pad((string) ((int) substr($last, 11, 6) + 1), 6, '0', STR_PAD_LEFT);
        }
        $file = rtrim($this->path, '/\\') . DIRECTORY_SEPARATOR . $prefix . "_$name.php";

        $up = $table
            ? "        Schema::create('$table', function (Blueprint \$table) {\n            \$table->id();\n            \$table->timestamps();\n        });"
            : "        Schema::table('table_name', function (Blueprint \$table) {\n            //\n        });";
        $down = $table ? "        Schema::dropIfExists('$table');" : "        //";

        file_put_contents($file, "<?php\n\nuse System\\Database\\Migration;\nuse System\\Database\\Schema\\Blueprint;\nuse System\\Database\\Schema\\Schema;\n\n"
            . "return new class extends Migration\n{\n    public function up(): void\n    {\n$up\n    }\n\n    public function down(): void\n    {\n$down\n    }\n};\n");

        return $file;
    }

    protected function resolve(string $file): Migration
    {
        $migration = require $file;
        if (!$migration instanceof Migration) {
            throw new RuntimeException(basename($file) . ' must return an instance of ' . Migration::class);
        }
        return $migration;
    }
}
