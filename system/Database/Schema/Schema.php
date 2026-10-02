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
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        Connection::getInstance()->exec($blueprint->toCreateSql());
    }

    /** Change an existing table (add / drop / rename columns and indexes) */
    public static function table(string $table, callable $callback): void
    {
        $blueprint = new Blueprint($table);
        $callback($blueprint);
        $sql = $blueprint->toAlterSql();
        if ($sql !== null) {
            Connection::getInstance()->exec($sql);
        }
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
        Connection::getInstance()->exec('RENAME TABLE ' . Blueprint::wrap($from) . ' TO ' . Blueprint::wrap($to));
    }

    public static function hasTable(string $table): bool
    {
        $stmt = Connection::getInstance()->prepare(
            'SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
        );
        $stmt->execute([$table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    public static function hasColumn(string $table, string $column): bool
    {
        $stmt = Connection::getInstance()->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?'
        );
        $stmt->execute([$table, $column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    /** Run a callback with foreign key checks off (for drops in any order) */
    public static function withoutForeignKeyChecks(callable $callback): void
    {
        $pdo = Connection::getInstance();
        $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
        try {
            $callback();
        } finally {
            $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
        }
    }
}
