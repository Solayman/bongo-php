<?php
namespace System\Database\Schema;

/**
 * Create and change tables from PHP across default or named connections:
 *
 *   Schema::create('products', function (Blueprint $table) {
 *       $table->id();
 *       $table->string('name', 150);
 *       $table->timestamps();
 *   });
 *
 *   Schema::connection('analytics')->create('events', function (Blueprint $table) {
 *       $table->id();
 *       $table->string('type');
 *   });
 */
class Schema
{
    /** Return a SchemaBuilder scoped to a specific connection */
    public static function connection(?string $name = null): SchemaBuilder
    {
        return new SchemaBuilder($name);
    }

    public static function create(string $table, callable $callback): void
    {
        self::connection()->create($table, $callback);
    }

    /** Change an existing table (add / drop / rename columns and indexes) */
    public static function table(string $table, callable $callback): void
    {
        self::connection()->table($table, $callback);
    }

    public static function drop(string $table): void
    {
        self::connection()->drop($table);
    }

    public static function dropIfExists(string $table): void
    {
        self::connection()->dropIfExists($table);
    }

    public static function rename(string $from, string $to): void
    {
        self::connection()->rename($from, $to);
    }

    public static function hasTable(string $table): bool
    {
        return self::connection()->hasTable($table);
    }

    public static function hasColumn(string $table, string $column): bool
    {
        return self::connection()->hasColumn($table, $column);
    }

    /** Names of every table in the database, sorted */
    public static function tables(): array
    {
        return self::connection()->tables();
    }

    /** Run a callback with foreign key checks off (for drops in any order) */
    public static function withoutForeignKeyChecks(callable $callback): void
    {
        self::connection()->withoutForeignKeyChecks($callback);
    }
}
