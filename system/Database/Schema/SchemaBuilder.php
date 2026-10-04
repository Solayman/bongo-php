<?php
namespace System\Database\Schema;

use PDO;
use System\Database\Connection;

/**
 * Manages database schema operations for a specific connection.
 *
 *   Schema::connection('analytics')->create('events', function (Blueprint $table) {
 *       $table->id();
 *       $table->string('name');
 *   });
 */
class SchemaBuilder
{
    public function __construct(protected ?string $connection = null) {}

    public function getConnectionName(): ?string
    {
        return $this->connection ?? Connection::getDefaultConnection();
    }

    protected function pdo(): PDO
    {
        return Connection::getInstance($this->connection);
    }

    protected function driver(): string
    {
        return Connection::driver($this->connection);
    }

    protected function isSqlite(): bool
    {
        return Connection::isSqlite($this->connection);
    }

    protected function isPgsql(): bool
    {
        return Connection::isPgsql($this->connection);
    }

    public function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, $this->driver());
        $callback($blueprint);
        $this->run($blueprint->toCreateStatements());
    }

    public function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, $this->driver());
        $callback($blueprint);
        $this->run($blueprint->toAlterStatements());
    }

    /** $cascade (PostgreSQL): also drop foreign keys and views that depend on the table */
    public function drop(string $table, bool $cascade = false): void
    {
        $this->pdo()->exec('DROP TABLE ' . Blueprint::wrap($table, $this->driver()) . ($cascade && $this->isPgsql() ? ' CASCADE' : ''));
    }

    public function dropIfExists(string $table): void
    {
        $this->pdo()->exec('DROP TABLE IF EXISTS ' . Blueprint::wrap($table, $this->driver()));
    }

    public function rename(string $from, string $to): void
    {
        $sql = match (true) {
            $this->isSqlite(), $this->isPgsql() => 'ALTER TABLE ' . Blueprint::wrap($from, $this->driver()) . ' RENAME TO ' . Blueprint::wrap($to, $this->driver()),
            default => 'RENAME TABLE ' . Blueprint::wrap($from, $this->driver()) . ' TO ' . Blueprint::wrap($to, $this->driver()),
        };
        $this->pdo()->exec($sql);
    }

    public function hasTable(string $table): bool
    {
        return in_array($table, $this->tables(), true);
    }

    public function hasColumn(string $table, string $column): bool
    {
        if ($this->isSqlite()) {
            $stmt = $this->pdo()->prepare('SELECT COUNT(*) FROM pragma_table_info(?) WHERE name = ?');
        } elseif ($this->isPgsql()) {
            $stmt = $this->pdo()->prepare(
                'SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = current_schema() AND table_name = ? AND column_name = ?'
            );
        } else {
            $stmt = $this->pdo()->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
        }
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Names of every table in the database, sorted */
    public function tables(): array
    {
        $sql = match (true) {
            $this->isSqlite() => "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name",
            $this->isPgsql()  => "SELECT table_name FROM information_schema.tables WHERE table_schema = current_schema() AND table_type = 'BASE TABLE' ORDER BY table_name",
            default           => "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME",
        };
        return $this->pdo()->query($sql)->fetchAll(PDO::FETCH_COLUMN);
    }

    /** Run a callback with foreign key checks off (for drops in any order) */
    public function withoutForeignKeyChecks(callable $callback): void
    {
        $pdo = $this->pdo();
        $disable = match (true) {
            $this->isSqlite() => 'PRAGMA foreign_keys = OFF',
            $this->isPgsql()  => "SET session_replication_role = 'replica'",
            default           => 'SET FOREIGN_KEY_CHECKS = 0',
        };
        $enable = match (true) {
            $this->isSqlite() => 'PRAGMA foreign_keys = ON',
            $this->isPgsql()  => "SET session_replication_role = 'origin'",
            default           => 'SET FOREIGN_KEY_CHECKS = 1',
        };

        try {
            $pdo->exec($disable);
        } catch (\PDOException $e) {
            // PostgreSQL: session_replication_role needs a superuser. A normal app user runs the
            // callback with checks on (dropAllTables uses DROP ... CASCADE, so it still works).
            if (!$this->isPgsql()) throw $e;
            $callback();
            return;
        }
        try {
            $callback();
        } finally {
            $pdo->exec($enable);
        }
    }

    protected function run(array $statements): void
    {
        $pdo = $this->pdo();
        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }
    }
}
