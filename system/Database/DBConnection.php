<?php
namespace System\Database;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Represents a dedicated database connection.
 *
 *   $conn = DB::connection('tenant');
 *   $conn->table('orders')->where('status', 'pending')->get();
 *   $conn->transaction(function () use ($conn) { ... });
 */
class DBConnection
{
    /** @var array<string, self> */
    protected static array $instances = [];

    protected int $transactions = 0;

    public function __construct(protected string $name) {}

    public static function resolve(?string $name = null): self
    {
        $name = $name ?? Connection::getDefaultConnection();
        return self::$instances[$name] ??= new self($name);
    }

    public static function reset(?string $name = null): void
    {
        if ($name === null) {
            self::$instances = [];
        } else {
            unset(self::$instances[$name]);
        }
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getPdo(): PDO
    {
        return Connection::getInstance($this->name);
    }

    public function getDriver(): string
    {
        return Connection::driver($this->name);
    }

    public function isMysql(): bool
    {
        return Connection::isMysql($this->name);
    }

    public function isSqlite(): bool
    {
        return Connection::isSqlite($this->name);
    }

    public function isPgsql(): bool
    {
        return Connection::isPgsql($this->name);
    }

    /** A query builder bound to this connection */
    public function table(string $table): QueryBuilder
    {
        return (new QueryBuilder($table))->setConnection($this->name);
    }

    /**
     * Run a raw query. Returns rows for queries that produce a result set
     * (SELECT, SHOW, DESCRIBE, WITH ...), otherwise true/false.
     */
    public function raw(string $query, array $bindings = []): array|bool
    {
        $stmt = $this->run($query, $bindings);
        return $stmt->columnCount() > 0 ? $stmt->fetchAll() : true;
    }

    /** Prepare and execute one statement on this connection */
    public function run(string $sql, array $bindings = []): \PDOStatement
    {
        $start = microtime(true);
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare($sql);
        $isPgsql = $this->isPgsql();

        foreach (array_values($bindings) as $i => $value) {
            $stmt->bindValue($i + 1, is_bool($value) ? ($isPgsql ? $value : (int) $value) : $value, match (true) {
                is_bool($value) => $isPgsql ? PDO::PARAM_BOOL : PDO::PARAM_INT,
                is_int($value)  => PDO::PARAM_INT,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            });
        }
        $stmt->execute();

        DB::recordQuery($this->name, $sql, array_values($bindings), round((microtime(true) - $start) * 1000, 2));

        return $stmt;
    }

    /**
     * Run $callback in a transaction on this connection.
     * Nested calls use savepoints, so an inner failure only undoes the inner part.
     */
    public function transaction(callable $callback)
    {
        $this->beginTransaction();
        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (Throwable $e) {
            $this->rollBack();
            throw $e;
        }
    }

    public function beginTransaction(): void
    {
        $pdo = $this->getPdo();
        if ($this->transactions === 0) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec('SAVEPOINT ' . $this->savepointName());
        }
        $this->transactions++;
    }

    public function commit(): void
    {
        if ($this->transactions === 0) {
            throw new \LogicException("No active transaction to commit on database connection [{$this->name}]");
        }
        $this->transactions--;
        $pdo = $this->getPdo();
        if ($this->transactions === 0) {
            $pdo->commit();
        } else {
            $pdo->exec('RELEASE SAVEPOINT ' . $this->savepointName());
        }
    }

    public function rollBack(): void
    {
        if ($this->transactions === 0) {
            throw new \LogicException("No active transaction to roll back on database connection [{$this->name}]");
        }
        $this->transactions--;
        $pdo = $this->getPdo();
        if ($this->transactions === 0) {
            $pdo->rollBack();
        } else {
            $pdo->exec('ROLLBACK TO SAVEPOINT ' . $this->savepointName());
        }
    }

    public function transactionLevel(): int
    {
        return $this->transactions;
    }

    protected function savepointName(): string
    {
        $clean = preg_replace('/[^a-zA-Z0-9_]/', '_', $this->name);
        return "bongo_sp_{$clean}_{$this->transactions}";
    }
}
