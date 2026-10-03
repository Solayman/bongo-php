<?php
namespace System\Database;

use Throwable;

class DB
{
    protected static int $transactions = 0;
    protected static ?array $queryLog = null;

    /** A query builder for a table */
    public static function table(string $table): QueryBuilder
    {
        return new QueryBuilder($table);
    }

    /**
     * Run a raw query. Returns rows for queries that produce a result set
     * (SELECT, SHOW, DESCRIBE, WITH ...), otherwise true/false.
     */
    public static function raw(string $query, array $bindings = []): array|bool
    {
        $stmt = self::run($query, $bindings);
        return $stmt->columnCount() > 0 ? $stmt->fetchAll() : true;
    }

    /** Prepare and execute one statement (every query of the framework goes through here) */
    public static function run(string $sql, array $bindings = []): \PDOStatement
    {
        $start = microtime(true);
        $stmt = Connection::getInstance()->prepare($sql);
        // Bind with real types: SQLite compares the text '4' and the number 4 as different values
        foreach (array_values($bindings) as $i => $value) {
            $stmt->bindValue($i + 1, is_bool($value) ? (int) $value : $value, match (true) {
                is_int($value), is_bool($value) => \PDO::PARAM_INT,
                $value === null                 => \PDO::PARAM_NULL,
                default                         => \PDO::PARAM_STR,
            });
        }
        $stmt->execute();

        if (self::$queryLog !== null && count(self::$queryLog) < 500) {
            self::$queryLog[] = ['sql' => $sql, 'bindings' => array_values($bindings), 'ms' => round((microtime(true) - $start) * 1000, 2)];
        }
        return $stmt;
    }

    /** Start recording queries (SQL, bindings, milliseconds) */
    public static function enableQueryLog(): void
    {
        self::$queryLog = [];
    }

    public static function disableQueryLog(): void
    {
        self::$queryLog = null;
    }

    /** @return array<int, array{sql: string, bindings: array, ms: float}> */
    public static function getQueryLog(): array
    {
        return self::$queryLog ?? [];
    }

    public static function flushQueryLog(): void
    {
        if (self::$queryLog !== null) self::$queryLog = [];
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
    public static function transaction(callable $callback)
    {
        self::beginTransaction();
        try {
            $result = $callback();
            self::commit();
            return $result;
        } catch (Throwable $e) {
            self::rollBack();
            throw $e;
        }
    }

    public static function beginTransaction(): void
    {
        $pdo = Connection::getInstance();
        if (self::$transactions === 0) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec('SAVEPOINT bongo_sp' . self::$transactions);
        }
        self::$transactions++;
    }

    public static function commit(): void
    {
        if (self::$transactions === 0) {
            throw new \LogicException('No active transaction to commit');
        }
        self::$transactions--;
        $pdo = Connection::getInstance();
        if (self::$transactions === 0) {
            $pdo->commit();
        } else {
            $pdo->exec('RELEASE SAVEPOINT bongo_sp' . self::$transactions);
        }
    }

    public static function rollBack(): void
    {
        if (self::$transactions === 0) {
            throw new \LogicException('No active transaction to roll back');
        }
        self::$transactions--;
        $pdo = Connection::getInstance();
        if (self::$transactions === 0) {
            $pdo->rollBack();
        } else {
            $pdo->exec('ROLLBACK TO SAVEPOINT bongo_sp' . self::$transactions);
        }
    }

    public static function transactionLevel(): int
    {
        return self::$transactions;
    }
}
