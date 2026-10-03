<?php
namespace System\Database\Schema;

use System\Database\Connection;

/**
 * Create and change tables from PHP:
 *
 *   Schema::create('products', function (Blueprint $table) {
 *       $table->id();
 *       $table->string('name', 150);
 *       $table->timestamps();
 *   });
 */
class Schema
{
    public static function create(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, Connection::driver());
        $callback($blueprint);
        self::run($blueprint->toCreateStatements());
    }

    /** Change an existing table (add / drop / rename columns and indexes) */
    public static function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table, Connection::driver());
        $callback($blueprint);
        self::run($blueprint->toAlterStatements());
    }

    public static function drop(string $table): void
    {
        Connection::getInstance()->exec('DROP TABLE ' . Blueprint::wrap($table));
    }

    public static function dropIfExists(string $table): void
    {
        Connection::getInstance()->exec('DROP TABLE IF EXISTS ' . Blueprint::wrap($table));
    }

    public static function rename(string $from, string $to): void
    {
        $sql = Connection::isSqlite()
            ? 'ALTER TABLE ' . Blueprint::wrap($from) . ' RENAME TO ' . Blueprint::wrap($to)
            : 'RENAME TABLE ' . Blueprint::wrap($from) . ' TO ' . Blueprint::wrap($to);
        Connection::getInstance()->exec($sql);
    }

    public static function hasTable(string $table): bool
    {
        return in_array($table, self::tables(), true);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        if (Connection::isSqlite()) {
            $stmt = Connection::getInstance()->prepare('SELECT COUNT(*) FROM pragma_table_info(?) WHERE name = ?');
        } else {
            $stmt = Connection::getInstance()->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
            );
        }
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Names of every table in the database, sorted */
    public static function tables(): array
    {
        $sql = Connection::isSqlite()
            ? "SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name"
            : 'SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_TYPE = \'BASE TABLE\' ORDER BY TABLE_NAME';
        return Connection::getInstance()->query($sql)->fetchAll(\PDO::FETCH_COLUMN);
    }

    /** Run a callback with foreign key checks off (for drops in any order) */
    public static function withoutForeignKeyChecks(callable $callback): void
    {
        $pdo = Connection::getInstance();
        $pdo->exec(Connection::isSqlite() ? 'PRAGMA foreign_keys = OFF' : 'SET FOREIGN_KEY_CHECKS = 0');
        try {
            $callback();
        } finally {
            $pdo->exec(Connection::isSqlite() ? 'PRAGMA foreign_keys = ON' : 'SET FOREIGN_KEY_CHECKS = 1');
        }
    }

    protected static function run(array $statements): void
    {
        $pdo = Connection::getInstance();
        foreach ($statements as $sql) {
            $pdo->exec($sql);
        }
    }
}
