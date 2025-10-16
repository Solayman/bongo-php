<?php

namespace System\Database;

use PDO;

class QueryBuilder
{
    protected PDO $pdo;
    protected string $table;
    protected array $wheres = [];
    protected array $bindings = [];
    protected ?string $orderBy = null;
    protected ?int $limit = null;
    protected bool $asObject = true; // Default return type

    public function __construct(string $table)
    {
        $this->pdo = Connection::getInstance();
        $this->table = $table;
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
     * Add where condition
     */
    public function where(string $column, $operator = null, $value = null): self
    {

        if (func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->wheres[] = "$column $operator ?";
        $this->bindings[] = $value;

        return $this;
    }

    public function whereIn(string $column, array $values): self
    {
        if (empty($values)) {
            // যদি array খালি থাকে, তাহলে কোন ফলাফলই না ফেরায়
            $this->wheres[] = "1 = 0";
            return $this;
        }

        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = "$column IN ($placeholders)";
        $this->bindings = array_merge($this->bindings, $values);

        return $this;
    }



    /**
     * Order by column
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy = "$column $direction";
        return $this;
    }

    /**
     * Limit number of results
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * Build and execute SELECT query
     */
    public function get(): array
    {
        $sql = "SELECT * FROM {$this->table}";

        if ($this->wheres) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }

        if ($this->orderBy) {
            $sql .= " ORDER BY {$this->orderBy}";
        }

        if ($this->limit) {
            $sql .= " LIMIT {$this->limit}";
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($this->bindings);

        $fetchMode = $this->asObject ? PDO::FETCH_OBJ : PDO::FETCH_ASSOC;

        return $stmt->fetchAll($fetchMode);
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

    /**
     * Insert new record
     */
    public function insert(array $data): bool
    {
        $columns = implode(',', array_keys($data));
        $placeholders = implode(',', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO {$this->table} ($columns) VALUES ($placeholders)";

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute(array_values($data));
    }

    /**
     * Update record(s)
     */
    public function update(array $data): bool
    {
        $set = implode(',', array_map(fn($col) => "$col = ?", array_keys($data)));
        $sql = "UPDATE {$this->table} SET $set";

        if ($this->wheres) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([...array_values($data), ...$this->bindings]);
    }

    /**
     * Delete record(s)
     */
    public function delete(): bool
    {
        $sql = "DELETE FROM {$this->table}";
        if ($this->wheres) {
            $sql .= " WHERE " . implode(' AND ', $this->wheres);
        }

        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($this->bindings);
    }
}
