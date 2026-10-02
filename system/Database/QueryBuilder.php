<?php

namespace System\Database;

use InvalidArgumentException;
use PDO;

class QueryBuilder
{
    protected string $table;
    protected array $columns = ['*'];
    protected array $joins = [];
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $orders = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected bool $asObject = true; // Default return type

    protected const OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    protected function pdo(): PDO
    {
        return Connection::getInstance();
    }

    /**
     * Fetch results as array instead of object
     */
    public function asArray(): self
    {
        $this->asObject = false;
        return $this;
    }

    /**
     * Columns to select: select('id', 'name', 'users.email AS customer_email')
     */
    public function select(string ...$columns): self
    {
        $this->columns = $columns ?: ['*'];
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->assertOperator($operator);
        $this->joins[] = "$type JOIN $table ON $first $operator $second";
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * Add where condition: where('id', 5) or where('price', '>', 10)
     */
    public function where(string $column, $operator = null, $value = null): self
    {
        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->assertOperator($operator);
        $this->wheres[] = "$column $operator ?";
        $this->bindings[] = $value;

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            // Empty list: match nothing
            $this->wheres[] = "1 = 0";
            return $this;
        }

        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = "$column IN ($placeholders)";
        $this->bindings = array_merge($this->bindings, array_values($values));

        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = "$column IS NULL";
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = "$column IS NOT NULL";
        return $this;
    }

    /**
     * Raw condition, e.g. whereRaw('(name LIKE ? OR sku LIKE ?)', [$q, $q])
     */
    public function whereRaw(string $sql, array $bindings = []): self
    {
        $this->wheres[] = "($sql)";
        $this->bindings = array_merge($this->bindings, $bindings);
        return $this;
    }

    /**
     * Order by column (can be called several times)
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException("Invalid order direction [$direction]");
        }
        $this->orders[] = "$column $direction";
        return $this;
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'DESC');
    }

    /**
     * Limit number of results
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    protected function assertOperator(string $operator): void
    {
        if (!in_array(strtoupper($operator), self::OPERATORS, true)) {
            throw new InvalidArgumentException("Invalid operator [$operator]");
        }
    }

    protected function compileFromJoinsWheres(): string
    {
        $sql = " FROM {$this->table}";

        if ($this->joins) {
            $sql .= ' ' . implode(' ', $this->joins);
        }

        if ($this->wheres) {
            $sql .= ' WHERE ' . implode(' AND ', $this->wheres);
        }

        return $sql;
    }

    public function toSql(): string
    {
        $sql = 'SELECT ' . implode(', ', $this->columns) . $this->compileFromJoinsWheres();

        if ($this->orders) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return $sql;
    }

    /**
     * Build and execute SELECT query
     */
    public function get(): array
    {
        $stmt = $this->pdo()->prepare($this->toSql());
        $stmt->execute($this->bindings);

        return $stmt->fetchAll($this->asObject ? PDO::FETCH_OBJ : PDO::FETCH_ASSOC);
    }

    /**
     * Return first record
     */
    public function first(): object|array|null
    {
        $this->limit(1);
        $data = $this->get();
        return $data[0] ?? null;
    }

    protected function aggregate(string $expression)
    {
        $stmt = $this->pdo()->prepare("SELECT $expression AS aggregate" . $this->compileFromJoinsWheres());
        $stmt->execute($this->bindings);
        return $stmt->fetchColumn();
    }

    public function count(string $column = '*'): int
    {
        return (int) $this->aggregate("COUNT($column)");
    }

    public function sum(string $column): float
    {
        return (float) ($this->aggregate("SUM($column)") ?? 0);
    }

    /**
     * Paginate: ['data' => rows, 'total', 'per_page', 'current_page', 'last_page']
     */
    public function paginate(int $perPage = 15, ?int $page = null): array
    {
        $page = max(1, $page ?? (int) ($_GET['page'] ?? 1));
        $total = (clone $this)->count();
        $lastPage = max(1, (int) ceil($total / $perPage));
        $page = min($page, $lastPage);

        $data = $this->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return [
            'data'         => $data,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $lastPage,
        ];
    }

    /**
     * Insert new record
     */
    public function insert(array $data): bool
    {
        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";

        $stmt = $this->pdo()->prepare($sql);
        return $stmt->execute(array_values($data));
    }

    /**
     * Insert and return the new auto-increment id
     */
    public function insertGetId(array $data): int
    {
        $this->insert($data);
        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * Update record(s). Refuses to run without a where() unless $all is true.
     */
    public function update(array $data, bool $all = false): bool
    {
        $this->assertHasWhere($all, 'update');

        $set = implode(',', array_map(fn($col) => "$col = ?", array_keys($data)));
        $sql = "UPDATE {$this->table} SET $set";

        if ($this->wheres) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }

        $stmt = $this->pdo()->prepare($sql);
        return $stmt->execute([...array_values($data), ...$this->bindings]);
    }

    /**
     * Delete record(s). Refuses to run without a where() unless $all is true.
     */
    public function delete(bool $all = false): bool
    {
        $this->assertHasWhere($all, 'delete');

        $sql = "DELETE FROM {$this->table}";
        if ($this->wheres) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }

        $stmt = $this->pdo()->prepare($sql);
        return $stmt->execute($this->bindings);
    }

    protected function assertHasWhere(bool $all, string $operation): void
    {
        if (!$all && !$this->wheres) {
            throw new \LogicException("Refusing to $operation every row of {$this->table} without a where(). Pass true to allow it.");
        }
    }

    public function truncate(): bool
    {
        $sql = "TRUNCATE TABLE {$this->table}";
        return $this->pdo()->exec($sql) !== false;
    }
}
