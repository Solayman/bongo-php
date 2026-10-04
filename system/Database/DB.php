<?php

namespace System\Database;

use PDO;
use Throwable;

class DB
{
    protected static ?array $queryLog = null;

    /** Access a specific database connection */
    public static function connection(?string $name = null): DBConnection
    {
        return DBConnection::resolve($name);
    }

    /** A query builder for a table on the default or specified connection */
    public static function table(string $table, ?string $connection = null): QueryBuilder
    {
        return self::connection($connection)->table($table);
    }

    /**
     * Run a raw query. Returns rows for queries that produce a result set
     * (SELECT, SHOW, DESCRIBE, WITH ...), otherwise true/false.
     */
    public static function raw(string $query, array $bindings = [], ?string $connection = null): array|bool
    {
        return self::connection($connection)->raw($query, $bindings);
    }

    /** Prepare and execute one statement (every query of the framework goes through here) */
    public static function run(string $sql, array $bindings = [], ?string $connection = null): \PDOStatement
    {
        return self::connection($connection)->run($sql, $bindings);
    }

    /**
     * Run $callback in a transaction: committed when it returns, rolled back if it throws.
     * Nested calls use savepoints, so an inner failure only undoes the inner part.
     *
     *   DB::transaction(function () use ($order) {
     *       DB::table('orders')->insert($order);
     *       DB::table('products')->where('id', 5)->decrement('stock');
     *   });
     */
    public static function transaction(callable $callback, ?string $connection = null)
    {
        return self::connection($connection)->transaction($callback);
    }

    public static function beginTransaction(?string $connection = null): void
    {
        self::connection($connection)->beginTransaction();
    }

    public static function commit(?string $connection = null): void
    {
        self::connection($connection)->commit();
    }

    public static function rollBack(?string $connection = null): void
    {
        self::connection($connection)->rollBack();
    }

    public static function transactionLevel(?string $connection = null): int
    {
        return self::connection($connection)->transactionLevel();
    }

    /** The underlying PDO instance for the default or specified connection */
    public static function getPdo(?string $name = null): PDO
    {
        return Connection::getInstance($name);
    }

    /** The driver for the default or specified connection */
    public static function getDriver(?string $name = null): string
    {
        return Connection::driver($name);
    }

    /** Start recording queries (SQL, bindings, milliseconds) across connections */
    public static function enableQueryLog(): void
    {
        self::$queryLog = [];
    }

    public static function disableQueryLog(): void
    {
        self::$queryLog = null;
    }

    /** @return array<int, array{connection: string, sql: string, bindings: array, ms: float}> */
    public static function getQueryLog(): array
    {
        return self::$queryLog ?? [];
    }

    public static function flushQueryLog(): void
    {
        if (self::$queryLog !== null) {
            self::$queryLog = [];
        }
    }

    /** Record a query into the query log when active */
    public static function recordQuery(string $connection, string $sql, array $bindings, float $ms): void
    {
        if (self::$queryLog !== null && count(self::$queryLog) < 500) {
            self::$queryLog[] = [
                'connection' => $connection,
                'sql'        => $sql,
                'bindings'   => $bindings,
                'ms'         => $ms,
            ];
        }
    }
}
