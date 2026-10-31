<?php

namespace System\Database;

use InvalidArgumentException;
use PDO;

/**
 * Fluent SQL builder over PDO. Values are always bound with "?".
 *
 *   DB::table('orders')->where('status', 'paid')->orWhere(fn($q) => $q->where('total', '>', 100)->whereNull('user_id'))
 *       ->groupBy('user_id')->having('SUM(total)', '>', 500)->orderBy('user_id')->get();
 */
class QueryBuilder
{
    protected string $table;
    protected array $columns = ['*'];
    protected array $selectBindings = [];
    protected bool $distinct = false;
    protected array $joins = [];
    /** @var array<int, array{0: string, 1: string}> [boolean, sql] */
    protected array $wheres = [];
    protected array $bindings = [];
    protected array $groups = [];
    protected array $groupBindings = [];
    protected array $havings = [];
    protected array $havingBindings = [];
    protected array $orders = [];
    protected array $orderBindings = [];
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected bool $asObject = true;
    protected ?string $lock = null;
    /** Model class whose instances get() returns (set by Model::query()) */
    protected ?string $model = null;
    protected array $eager = [];
    /** Soft deletes: "products.deleted_at" when the model uses them; trashed rows are without / with / only */
    protected ?string $softDeleteColumn = null;
    protected string $trashed = 'without';
    protected ?string $connection = null;

    protected const OPERATORS = ['=', '!=', '<>', '<', '>', '<=', '>=', 'LIKE', 'NOT LIKE'];

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function setConnection(?string $connection): self
    {
        $this->connection = $connection;
        return $this;
    }

    public function getConnection(): ?string
    {
        return $this->connection;
    }

    protected function pdo(): PDO
    {
        return Connection::getInstance($this->connection);
    }

    /** Prepare + execute, recorded in the query log when it is enabled */
    protected function run(string $sql, array $bindings): \PDOStatement
    {
        return DB::connection($this->connection)->run($sql, $bindings);
    }

    protected function isPgsql(): bool
    {
        return Connection::isPgsql($this->connection);
    }

    protected function isSqlite(): bool
    {
        return Connection::isSqlite($this->connection);
    }

    protected function isMysql(): bool
    {
        return Connection::isMysql($this->connection);
    }

    public function table(): string
    {
        return $this->table;
    }

    /** Return rows as instances of this Model class */
    public function setModel(?string $class): self
    {
        $this->model = $class;
        if ($class !== null && method_exists($class, 'usesSoftDeletes') && $class::usesSoftDeletes()) {
            $this->softDeleteColumn = "{$this->table}.deleted_at";
        }
        return $this;
    }

    /**
     * Query scopes: Product::query()->active() calls Product::scopeActive($query).
     * Anything else is an unknown method.
     */
    public function __call(string $method, array $args)
    {
        $scope = 'scope' . ucfirst($method);
        if ($this->model !== null && method_exists($this->model, $scope)) {
            (new $this->model())->$scope($this, ...$args);
            return $this;
        }
        throw new \BadMethodCallException("Call to undefined method " . static::class . "::$method()"
            . ($this->model ? " (no scope $scope() on {$this->model} either)" : ''));
    }

    /** Include soft-deleted rows */
    public function withTrashed(): self
    {
        $this->trashed = 'with';
        return $this;
    }

    /** Only soft-deleted rows */
    public function onlyTrashed(): self
    {
        $this->trashed = 'only';
        return $this;
    }

    /** Really DELETE the rows, even for a model with soft deletes */
    public function forceDelete(bool $all = false): bool
    {
        $column = $this->softDeleteColumn;
        $this->softDeleteColumn = null;
        try {
            return $this->delete($all);
        } finally {
            $this->softDeleteColumn = $column;
        }
    }

    /** Bring soft-deleted rows back */
    public function restore(): bool
    {
        return $this->onlyTrashed()->update([$this->softDeleteColumn ?? 'deleted_at' => null]);
    }

    /** Eager-load relations on the returned models: ->with('items', 'user') */
    public function with(string ...$relations): self
    {
        $this->eager = array_merge($this->eager, $relations);
        return $this;
    }

    /** Fetch results as arrays instead of objects */
    public function asArray(): self
    {
        $this->asObject = false;
        return $this;
    }

    // ------------------------------------------------------------------
    // SELECT parts
    // ------------------------------------------------------------------

    /** Columns to select: select('id', 'name', 'users.email AS customer_email') */
    public function select(string ...$columns): self
    {
        $this->columns = $columns ?: ['*'];
        $this->selectBindings = [];
        return $this;
    }

    public function selectRaw(string $sql, array $bindings = []): self
    {
        if ($this->columns === ['*']) {
            $this->columns = [$sql];
        } else {
            $this->columns[] = $sql;
        }
        $this->selectBindings = array_merge($this->selectBindings, $bindings);
        return $this;
    }

    public function addSelect(string ...$columns): self
    {
        if ($this->columns === ['*']) {
            $this->columns = $columns;
        } else {
            $this->columns = array_merge($this->columns, $columns);
        }
        return $this;
    }

    public function distinct(): self
    {
        $this->distinct = true;
        return $this;
    }

    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->assertOperator($operator);
        $this->joins[] = "$type JOIN " . $this->wrapTable($table) . ' ON ' . $this->column($first) . " $operator " . $this->column($second);
        return $this;
    }

    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    // ------------------------------------------------------------------
    // WHERE
    // ------------------------------------------------------------------

    /**
     * where('id', 5) · where('price', '>', 10) · where(fn($q) => $q->where(...)->orWhere(...)) for a group in ( )
     */
    public function where($column, $operator = null, $value = null): self
    {
        return $this->addWhere('AND', func_get_args());
    }

    public function orWhere($column, $operator = null, $value = null): self
    {
        return $this->addWhere('OR', func_get_args());
    }

    protected function addWhere(string $boolean, array $args): self
    {
        if ($args[0] instanceof \Closure) {
            $group = new static($this->table);
            $args[0]($group);
            if ($group->wheres) {
                $this->wheres[] = [$boolean, '(' . $group->compileWheres(false) . ')'];
                $this->bindings = array_merge($this->bindings, $group->bindings);
            }
            return $this;
        }

        [$column, $operator, $value] = array_pad($args, 3, null);
        if (count($args) === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->assertOperator((string) $operator);
        $column = $this->column((string) $column);
        $this->wheres[] = [$boolean, "$column $operator ?"];
        $this->bindings[] = $value;
        return $this;
    }

    public function whereIn(string $column, array $values, string $boolean = 'AND', bool $not = false): self
    {
        $column = $this->column($column);
        if (empty($values)) {
            // An empty list matches nothing (or everything for NOT IN)
            $this->wheres[] = [$boolean, $not ? '1 = 1' : '1 = 0'];
            return $this;
        }

        $placeholders = implode(',', array_fill(0, count($values), '?'));
        $this->wheres[] = [$boolean, "$column " . ($not ? 'NOT IN' : 'IN') . " ($placeholders)"];
        $this->bindings = array_merge($this->bindings, array_values($values));
        return $this;
    }

    public function whereNotIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, 'AND', true);
    }

    public function orWhereIn(string $column, array $values): self
    {
        return $this->whereIn($column, $values, 'OR');
    }

    public function whereBetween(string $column, $from, $to): self
    {
        $this->wheres[] = ['AND', $this->column($column) . ' BETWEEN ? AND ?'];
        array_push($this->bindings, $from, $to);
        return $this;
    }

    public function whereNull(string $column): self
    {
        $this->wheres[] = ['AND', $this->column($column) . ' IS NULL'];
        return $this;
    }

    public function whereNotNull(string $column): self
    {
        $this->wheres[] = ['AND', $this->column($column) . ' IS NOT NULL'];
        return $this;
    }

    public function orWhereNull(string $column): self
    {
        $this->wheres[] = ['OR', $this->column($column) . ' IS NULL'];
        return $this;
    }

    public function wrapIdentifier(string $identifier): string
    {
        $char = ($this->isPgsql() || $this->isSqlite()) ? '"' : '`';
        if (str_starts_with($identifier, $char) && str_ends_with($identifier, $char)) {
            return $identifier;
        }
        return $char . str_replace($char, $char . $char, $identifier) . $char;
    }

    public function wrapTable(string $table): string
    {
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $table, $m)) {
            return $this->wrapIdentifier($m[1]) . ' AS ' . $this->wrapIdentifier($m[2]);
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $table)) {
            return $this->wrapIdentifier($table);
        }
        return $table;
    }

    protected function wrapSelectColumn(string $column): string
    {
        if ($column === '*') {
            return '*';
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*)\.\*$/', $column, $m)) {
            return $this->wrapIdentifier($m[1]) . '.*';
        }
        if (preg_match('/^([A-Za-z_][A-Za-z0-9_]*(?:\.[A-Za-z_][A-Za-z0-9_]*)?)\s+AS\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $column, $m)) {
            return $this->column($m[1]) . ' AS ' . $this->wrapIdentifier($m[2]);
        }
        if (preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $column)) {
            return $this->column($column);
        }
        return $column;
    }

    /**
     * A column name placed in SQL: letters, digits and _ with an optional "table." prefix.
     * Column names cannot be bound like values, so one built from user input, e.g.
     * orderBy($request->sort), would be SQL injection. Use whereRaw() for SQL expressions.
     */
    protected function column(string $name): string
    {
        $char = ($this->isPgsql() || $this->isSqlite()) ? '"' : '`';
        $unquoted = str_replace($char, '', $name);
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*(\.[A-Za-z_][A-Za-z0-9_]*)?$/', $unquoted)) {
            throw new InvalidArgumentException("Invalid column name [$name]. Use whereRaw() for SQL expressions.");
        }
        $parts = explode('.', $unquoted);
        return implode('.', array_map([$this, 'wrapIdentifier'], $parts));
    }

    /** Raw condition, e.g. whereRaw('(name LIKE ? OR sku LIKE ?)', [$q, $q]) */
    public function whereRaw(string $sql, array $bindings = [], string $boolean = 'AND'): self
    {
        $this->wheres[] = [$boolean, "($sql)"];
        $this->bindings = array_merge($this->bindings, $bindings);
        return $this;
    }

    public function orWhereRaw(string $sql, array $bindings = []): self
    {
        return $this->whereRaw($sql, $bindings, 'OR');
    }

    // ------------------------------------------------------------------
    // GROUP / HAVING / ORDER / LIMIT
    // ------------------------------------------------------------------

    public function groupByRaw(string $sql, array $bindings = []): self
    {
        $this->groups[] = $sql;
        $this->groupBindings = array_merge($this->groupBindings, $bindings);
        return $this;
    }

    public function groupBy(string ...$columns): self
    {
        $this->groups = array_merge($this->groups, array_map(fn($c) => $this->column($c), $columns));
        return $this;
    }

    /** having('COUNT(*)', '>', 2) */
    public function having(string $expression, string $operator, $value): self
    {
        $this->assertOperator($operator);
        $this->havings[] = "$expression $operator ?";
        $this->havingBindings[] = $value;
        return $this;
    }

    public function havingRaw(string $sql, array $bindings = []): self
    {
        $this->havings[] = "($sql)";
        $this->havingBindings = array_merge($this->havingBindings, $bindings);
        return $this;
    }

    /** Order by column (can be called several times) */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $direction = strtoupper($direction);
        if (!in_array($direction, ['ASC', 'DESC'], true)) {
            throw new InvalidArgumentException("Invalid order direction [$direction]");
        }
        $this->orders[] = $this->column($column) . " $direction";
        return $this;
    }

    public function orderByRaw(string $sql, array $bindings = []): self
    {
        $this->orders[] = $sql;
        $this->orderBindings = array_merge($this->orderBindings, $bindings);
        return $this;
    }

    public function latest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'DESC');
    }

    public function oldest(string $column = 'created_at'): self
    {
        return $this->orderBy($column, 'ASC');
    }

    /**
     * Lock the selected rows until the transaction ends (SELECT ... FOR UPDATE).
     * Use inside DB::transaction() to stop two requests from changing the same rows at once.
     */
    public function lockForUpdate(): self
    {
        $this->lock = ' FOR UPDATE';
        return $this;
    }

    /** Shared lock: others can read but not change the rows until the transaction ends */
    public function sharedLock(): self
    {
        $this->lock = $this->isPgsql() ? ' FOR SHARE' : ' LOCK IN SHARE MODE';
        return $this;
    }

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

    // ------------------------------------------------------------------
    // SQL
    // ------------------------------------------------------------------

    protected function compileWheres(bool $withKeyword = true): string
    {
        $sql = '';
        foreach ($this->wheres as $i => [$boolean, $clause]) {
            $sql .= ($i === 0 ? '' : " $boolean ") . $clause;
        }
        // Soft deletes (top level only; wraps the rest so an OR can't bring trashed rows back)
        if ($withKeyword && $this->softDeleteColumn !== null && $this->trashed !== 'with') {
            $trashed = $this->column($this->softDeleteColumn) . ($this->trashed === 'only' ? ' IS NOT NULL' : ' IS NULL');
            $sql = $sql === '' ? $trashed : "($sql) AND $trashed";
        }
        if ($sql === '') {
            return '';
        }
        return ($withKeyword ? ' WHERE ' : '') . $sql;
    }

    protected function compileFromJoinsWheres(): string
    {
        $sql = " FROM " . $this->wrapTable($this->table);
        if ($this->joins) {
            $sql .= ' ' . implode(' ', $this->joins);
        }
        return $sql . $this->compileWheres();
    }

    protected function compileGroupsHavings(): string
    {
        $sql = '';
        if ($this->groups) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groups);
        }
        if ($this->havings) {
            $sql .= ' HAVING ' . implode(' AND ', $this->havings);
        }
        return $sql;
    }

    public function toSql(): string
    {
        $cols = array_map(fn($c) => $this->wrapSelectColumn($c), $this->columns);
        $sql = 'SELECT ' . ($this->distinct ? 'DISTINCT ' : '') . implode(', ', $cols)
            . $this->compileFromJoinsWheres() . $this->compileGroupsHavings();

        if ($this->orders) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }
        if ($this->offset !== null) {
            $sql .= ($this->limit === null && $this->isSqlite() ? ' LIMIT -1' : '') . " OFFSET {$this->offset}";
        }
        // SQLite locks the whole file during a write transaction, so row locks are not needed (or valid)
        return $sql . ($this->isSqlite() ? '' : ($this->lock ?? ''));
    }

    /** All bindings in SQL order */
    public function getBindings(): array
    {
        return array_merge(
            $this->selectBindings,
            $this->bindings,
            $this->groupBindings,
            $this->havingBindings,
            $this->orderBindings
        );
    }

    // ------------------------------------------------------------------
    // Reading
    // ------------------------------------------------------------------

    public function get(): array
    {
        $stmt = $this->run($this->toSql(), $this->getBindings());

        if ($this->model !== null) {
            $models = array_map(function ($row) {
                $model = ($this->model)::hydrate($row);
                if ($this->connection !== null) {
                    $model->setConnection($this->connection);
                }
                return $model;
            }, $stmt->fetchAll(PDO::FETCH_ASSOC));
            if ($this->eager && $models) {
                ($this->model)::eagerLoad($models, $this->eager);
            }
            return $models;
        }

        return $stmt->fetchAll($this->asObject ? PDO::FETCH_OBJ : PDO::FETCH_ASSOC);
    }

    public function first(): object|array|null
    {
        $data = (clone $this)->limit(1)->get();
        return $data[0] ?? null;
    }

    /** Values of one column: pluck('name') or keyed by another column: pluck('name', 'id') */
    public function pluck(string $column, ?string $key = null): array
    {
        $clone = (clone $this)->setModel(null)->asArray();
        // Reuse a matching select() expression ("COUNT(*) AS n" for pluck('n'))
        $expression = function (string $name) {
            foreach ($this->columns as $selected) {
                if ($selected !== '*' && self::resultKey($selected) === self::resultKey($name)) {
                    return $selected;
                }
            }
            return $this->column($name);
        };
        $clone->columns = $key ? [$expression($column), $expression($key)] : [$expression($column)];
        $clone->selectBindings = [];

        $out = [];
        foreach ($clone->get() as $row) {
            $value = $row[self::resultKey($column)] ?? null;
            if ($key === null) {
                $out[] = $value;
            } else {
                $out[$row[self::resultKey($key)] ?? ''] = $value;
            }
        }
        return $out;
    }

    /** The key PDO uses for a selected column: "users.name" → "name", "COUNT(*) AS n" → "n" */
    protected static function resultKey(string $column): string
    {
        if (preg_match('/\s+AS\s+[`"]?(\w+)[`"]?$/i', $column, $m)) {
            return $m[1];
        }
        $dot = strrpos($column, '.');
        $raw = $dot === false ? $column : substr($column, $dot + 1);
        return trim($raw, '`"');
    }

    /** One column of the first row */
    public function value(string $column)
    {
        $values = (clone $this)->limit(1)->pluck($column);
        return $values[0] ?? null;
    }

    public function exists(): bool
    {
        $bindings = array_merge($this->bindings, $this->groupBindings, $this->havingBindings);
        $stmt = $this->run('SELECT EXISTS(SELECT 1' . $this->compileFromJoinsWheres() . $this->compileGroupsHavings() . ') AS e', $bindings);
        return (bool) $stmt->fetchColumn();
    }

    public function doesntExist(): bool
    {
        return !$this->exists();
    }

    protected function aggregate(string $expression)
    {
        if ($this->groups) {
            // Aggregate over the grouped result
            $inner = (clone $this);
            $inner->orders = [];
            $inner->orderBindings = [];
            $inner->limit = $inner->offset = null;
            $inner->model = null;
            if ($inner->columns === ['*']) {
                $inner->columns = $inner->groups; // valid under ONLY_FULL_GROUP_BY
            }
            $agg = str_starts_with($expression, 'COUNT(') ? 'COUNT(*)' : $expression;
            $sql = "SELECT $agg AS aggregate FROM (" . $inner->toSql() . ') AS grouped';
        } else {
            $sql = "SELECT $expression AS aggregate" . $this->compileFromJoinsWheres();
        }
        $stmt = $this->run($sql, $this->groups ? $inner->getBindings() : $this->bindings);
        return $stmt->fetchColumn();
    }

    public function count(string $column = '*'): int
    {
        $primaryKey = ($this->model !== null && method_exists($this->model, 'primaryKey'))
            ? ($this->model)::primaryKey()
            : 'id';

        $tableRef = $this->table;
        if (preg_match('/\s+as\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $tableRef, $m) || preg_match('/\s+([A-Za-z_][A-Za-z0-9_]*)$/i', $tableRef, $m)) {
            $tableRef = $m[1];
        }

        if ($column === '*') {
            if ($this->distinct || !empty($this->joins)) {
                $target = 'DISTINCT ' . $this->column("$tableRef.$primaryKey");
            } else {
                $target = '*';
            }
        } else {
            $target = ($this->distinct ? 'DISTINCT ' : '') . $this->column($column);
        }

        return (int) $this->aggregate("COUNT($target)");
    }

    public function sum(string $column): float
    {
        return (float) ($this->aggregate("SUM(" . $this->column($column) . ")") ?? 0);
    }

    public function avg(string $column): ?float
    {
        $value = $this->aggregate("AVG(" . $this->column($column) . ")");
        return $value === null ? null : (float) $value;
    }

    public function max(string $column)
    {
        return $this->aggregate("MAX(" . $this->column($column) . ")");
    }

    public function min(string $column)
    {
        return $this->aggregate("MIN(" . $this->column($column) . ")");
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

        $data = (clone $this)->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return [
            'data'         => $data,
            'total'        => $total,
            'per_page'     => $perPage,
            'current_page' => $page,
            'last_page'    => $lastPage,
        ];
    }

    /**
     * Process rows in batches to keep memory low: chunk(500, function (array $rows) { ... })
     * Return false from the callback to stop.
     */
    public function chunk(int $size, callable $callback): void
    {
        for ($page = 0;; $page++) {
            $rows = (clone $this)->limit($size)->offset($page * $size)->get();
            if (!$rows || $callback($rows, $page + 1) === false || count($rows) < $size) {
                return;
            }
        }
    }

    // ------------------------------------------------------------------
    // Writing
    // ------------------------------------------------------------------

    /**
     * Insert rows, or update them when the unique key already exists (one query):
     *   DB::table('products')->upsert([['sku' => 'A1', 'price' => 5], ...], ['price'], ['sku']);
     * $update: the columns to change for existing rows (default: every column given).
     * $uniqueBy: the conflict target columns (for PostgreSQL/SQLite; default: 'id' or first column).
     * Returns how many rows were affected.
     */
    /**
     * Insert rows, or update them when the unique key already exists (one query):
     *   DB::table('products')->upsert($rows, ['sku'], ['price', 'stock']); // Laravel convention
     *   DB::table('products')->upsert($rows, ['price', 'stock'], ['sku']); // Legacy Bongo convention
     *   DB::table('products')->upsert($rows, ['price']);                    // update only
     */
    public function upsert(array $rows, array|string|null $arg2 = null, array|string|null $arg3 = null): int
    {
        if ($rows === []) {
            return 0;
        }
        $rows = isset($rows[0]) && is_array($rows[0]) ? $rows : [$rows];
        $rawColumns = array_keys($rows[0]);

        [$uniqueBy, $update] = $this->resolveUpsertArgs($rawColumns, $arg2, $arg3);

        $columns = array_map(fn($c) => $this->column((string) $c), $rawColumns);
        $update = $update === null ? null : array_map(fn($c) => $this->column((string) $c), (array) $update);
        $uniqueBy = $uniqueBy === null ? null : array_map(fn($c) => $this->column((string) $c), (array) $uniqueBy);
        $placeholders = '(' . implode(',', array_fill(0, count($columns), '?')) . ')';
        $bindings = [];
        foreach ($rows as $row) {
            if (array_keys($row) !== $rawColumns) {
                throw new \InvalidArgumentException('upsert(): every row needs the same columns in the same order');
            }
            array_push($bindings, ...array_values($row));
        }
        $sql = "INSERT INTO " . $this->wrapTable($this->table) . ' (' . implode(',', $columns) . ') VALUES '
            . implode(',', array_fill(0, count($rows), $placeholders));
        $updateCols = $update ?? $columns;

        if ($this->isSqlite()) {
            $target = $uniqueBy ? '(' . implode(', ', (array) $uniqueBy) . ')' : '';
            $sql .= " ON CONFLICT $target DO UPDATE SET " . implode(', ', array_map(fn($c) => "$c = excluded.$c", $updateCols));
        } elseif ($this->isPgsql()) {
            // PostgreSQL needs the conflict columns: use $uniqueBy, or find the table's unique index they fill
            $targetCols = $uniqueBy ? (array) $uniqueBy : array_map([$this, 'wrapIdentifier'], $this->pgsqlUniqueKey($rawColumns));
            // Same (unquoted) names as the INSERT list, so both fold to the same case
            $sql .= ' ON CONFLICT (' . implode(', ', $targetCols) . ') DO UPDATE SET '
                . implode(', ', array_map(fn($c) => "$c = EXCLUDED.$c", $updateCols));
        } else {
            $sql .= ' ON DUPLICATE KEY UPDATE ' . implode(', ', array_map(fn($c) => "$c = VALUES($c)", $updateCols));
        }

        try {
            return $this->run($sql, $bindings)->rowCount();
        } catch (\PDOException $e) {
            // Reliable cross-database fallback (SELECT + INSERT/UPDATE) when conflict syntax or target is unsupported
            return $this->fallbackUpsert($rows, $uniqueBy, $updateCols);
        }
    }

    protected function resolveUpsertArgs(array $columns, array|string|null $arg2, array|string|null $arg3): array
    {
        if ($arg2 === null && $arg3 === null) {
            return [null, null];
        }

        if ($arg3 !== null) {
            $a = (array) $arg2;
            $b = (array) $arg3;
            if ($this->looksLikeUniqueTarget($b) && !$this->looksLikeUniqueTarget($a)) {
                return [$b, $a];
            }
            return [$a, $b];
        }

        $a = (array) $arg2;
        if ($this->looksLikeUniqueTarget($a)) {
            return [$a, null];
        }
        return [null, $a];
    }

    protected function looksLikeUniqueTarget(array $cols): bool
    {
        $cols = array_map(fn($c) => trim((string) $c, '`"'), $cols);
        if ($cols === ['id'] || in_array('id', $cols, true)) {
            return true;
        }
        try {
            if ($this->isPgsql()) {
                return (bool) $this->pgsqlUniqueKey($cols);
            }
            if ($this->isMysql()) {
                $indexes = $this->run("SHOW INDEX FROM " . $this->wrapTable($this->table) . " WHERE Non_unique = 0", [])->fetchAll(\PDO::FETCH_ASSOC);
                $byName = [];
                foreach ($indexes as $row) {
                    $byName[$row['Key_name']][] = $row['Column_name'];
                }
                foreach ($byName as $keyCols) {
                    if (!array_diff($keyCols, $cols) && !array_diff($cols, $keyCols)) {
                        return true;
                    }
                }
            }
            if ($this->isSqlite()) {
                $indexes = $this->run("PRAGMA index_list('{$this->table}')", [])->fetchAll(PDO::FETCH_ASSOC);
                foreach ($indexes as $idx) {
                    if (!empty($idx['unique'])) {
                        $info = $this->run("PRAGMA index_info('{$idx['name']}')", [])->fetchAll(PDO::FETCH_ASSOC);
                        $idxCols = array_column($info, 'name');
                        if (!array_diff($idxCols, $cols) && !array_diff($cols, $idxCols)) {
                            return true;
                        }
                    }
                }
            }
        } catch (\Throwable) {
        }
        return false;
    }

    protected function fallbackUpsert(array $rows, ?array $uniqueBy, array $updateCols): int
    {
        $affected = 0;
        foreach ($rows as $row) {
            $matchCols = $uniqueBy ?: (array_key_exists('id', $row) ? ['id'] : [array_key_first($row)]);
            $query = DB::table($this->table)->setConnection($this->connection);
            foreach ($matchCols as $col) {
                $rawCol = trim((string) $col, '`"');
                if (array_key_exists($rawCol, $row)) {
                    $query->where($rawCol, $row[$rawCol]);
                }
            }
            if ($query->exists()) {
                $updateData = [];
                foreach ($updateCols as $col) {
                    $rawCol = trim((string) $col, '`"');
                    if (array_key_exists($rawCol, $row)) {
                        $updateData[$rawCol] = $row[$rawCol];
                    }
                }
                if ($updateData) {
                    $query->update($updateData);
                    $affected++;
                }
            } else {
                DB::table($this->table)->setConnection($this->connection)->insert($row);
                $affected++;
            }
        }
        return $affected;
    }

    /** The columns of a unique index (or primary key) of this table that $columns all fill */
    protected function pgsqlUniqueKey(array $columns): array
    {
        $indexes = $this->run(
            'SELECT array_to_string(array_agg(a.attname ORDER BY k.n), \',\') AS cols
             FROM pg_index i
             JOIN LATERAL unnest(i.indkey) WITH ORDINALITY AS k(attnum, n) ON true
             JOIN pg_attribute a ON a.attrelid = i.indrelid AND a.attnum = k.attnum
             WHERE i.indrelid = ?::regclass AND i.indisunique
             GROUP BY i.indexrelid, i.indisprimary ORDER BY i.indisprimary',
            [$this->table]
        )->fetchAll(PDO::FETCH_COLUMN);
        foreach ($indexes as $index) {
            $keys = explode(',', (string) $index);
            if (!array_diff($keys, $columns)) {
                return $keys;
            }
        }
        throw new InvalidArgumentException(
            "upsert() on {$this->table}: none of its unique keys is among the given columns. Pass them as the third argument: upsert(\$rows, \$update, ['sku'])"
        );
    }

    /**
     * Add "{relation}_count" columns: Category::withCount('products')->get() → $category->products_count.
     * Works for hasMany, hasOne and belongsToMany relations of the model.
     */
    public function withCount(...$relations): self
    {
        if (count($relations) === 1 && is_array($relations[0])) {
            $relations = $relations[0];
        }
        if ($this->model === null) {
            throw new \LogicException('withCount() needs a model query: Category::withCount(\'products\')');
        }
        if ($this->columns === ['*']) {
            $this->columns = ["{$this->table}.*"];
        }
        $model = new $this->model();
        foreach ($relations as $name) {
            if (!method_exists($model, $name)) {
                throw new \BadMethodCallException("withCount('$name'): {$this->model} has no $name() relation");
            }
            $ref = new \ReflectionMethod($model, $name);
            if ($ref->getDeclaringClass()->getName() === Model::class || $ref->getNumberOfRequiredParameters() > 0) {
                throw new \BadMethodCallException("withCount('$name'): {$this->model}::$name is not a relation");
            }
            $relation = $model->$name();
            if (!$relation instanceof Relation) {
                throw new \BadMethodCallException("withCount('$name'): {$this->model}::$name() is not a relation");
            }
            $this->columns[] = $relation->countSubquery($this->table) . " AS {$name}_count";
        }
        return $this;
    }

    /** Create a new model instance on this connection and save it */
    public function create(array $attributes = []): Model
    {
        if ($this->model === null) {
            throw new \LogicException('create() on QueryBuilder requires a model context');
        }
        $model = new ($this->model)($attributes);
        if ($this->connection !== null) {
            $model->setConnection($this->connection);
        }
        $model->save();
        return $model;
    }

    /**
     * Insert one row, or several in one statement:
     *   insert(['name' => 'A'])
     *   insert([['name' => 'A'], ['name' => 'B']])   // every row needs the same columns
     */
    public function insert(array $data): bool
    {
        if ($data === []) {
            return true;
        }
        $rows = isset($data[0]) && is_array($data[0]) ? $data : [$data];
        $keys = array_keys($rows[0]);
        $columns = implode(',', array_map(fn($c) => $this->column((string) $c), $keys));
        $placeholders = '(' . implode(',', array_fill(0, count($keys), '?')) . ')';
        $bindings = [];
        foreach ($rows as $row) {
            if (array_keys($row) !== $keys) {
                throw new InvalidArgumentException('insert(): every row needs the same columns in the same order');
            }
            array_push($bindings, ...array_values($row));
        }
        $this->run("INSERT INTO " . $this->wrapTable($this->table) . " ($columns) VALUES " . implode(',', array_fill(0, count($rows), $placeholders)), $bindings);
        return true;
    }

    /** Insert and return the new auto-increment id */
    public function insertGetId(array $data, string $idColumn = 'id'): int
    {
        if ($this->isPgsql()) {
            $columns = implode(',', array_map(fn($c) => $this->column((string) $c), array_keys($data)));
            $placeholders = implode(',', array_fill(0, count($data), '?'));
            $sql = "INSERT INTO " . $this->wrapTable($this->table) . " ($columns) VALUES ($placeholders) RETURNING " . $this->wrapIdentifier($idColumn);
            $stmt = $this->run($sql, array_values($data));
            return (int) $stmt->fetchColumn();
        }

        $this->insert($data);
        return (int) $this->pdo()->lastInsertId();
    }

    /** Update record(s). Refuses to run without a where() unless $all is true. */
    public function update(array $data, bool $all = false): bool
    {
        $this->assertHasWhere($all, 'update');

        // SQLite and PostgreSQL do not accept "table.column" on the left of SET
        $stripTable = function (string $key): string {
            if (str_starts_with($key, "{$this->table}.")) {
                return substr($key, strlen($this->table) + 1);
            }
            return $key;
        };
        $set = implode(',', array_map(fn($col) => $this->column($stripTable((string) $col)) . ' = ?', array_keys($data)));
        $this->run("UPDATE " . $this->wrapTable($this->table) . " SET $set" . $this->compileWheres(), [...array_values($data), ...$this->bindings]);
        return true;
    }

    /** Add to a column atomically: increment('stock', 5), optionally updating other columns too */
    public function increment(string $column, int|float $amount = 1, array $extra = []): bool
    {
        $this->assertHasWhere(false, 'increment');
        $col = $this->column($column);
        $set = ["$col = $col + ?"];
        foreach (array_keys($extra) as $c) {
            $set[] = $this->column((string) $c) . ' = ?';
        }
        $this->run("UPDATE " . $this->wrapTable($this->table) . " SET " . implode(', ', $set) . $this->compileWheres(), [$amount, ...array_values($extra), ...$this->bindings]);
        return true;
    }

    public function decrement(string $column, int|float $amount = 1, array $extra = []): bool
    {
        return $this->increment($column, -$amount, $extra);
    }

    /**
     * Delete record(s).
     *
     *   DB::table('users')->where('id', 5)->delete();
     *   DB::table('users')->delete(5);       // deletes by id
     *   DB::table('logs')->delete();          // deletes all records (or use truncate())
     *   DB::table('logs')->delete(false);     // refuses without a where()
     */
    public function delete(int|string|bool|null $idOrAll = null): bool
    {
        if (is_int($idOrAll) || (is_string($idOrAll) && $idOrAll !== '')) {
            return $this->where('id', $idOrAll)->delete();
        }
        if ($idOrAll === false) {
            $this->assertHasWhere(false, 'delete');
        }
        if ($this->softDeleteColumn !== null) {
            // A model with soft deletes: mark the rows instead of removing them
            return $this->update([$this->softDeleteColumn => date('Y-m-d H:i:s')], true);
        }
        $this->run("DELETE FROM " . $this->wrapTable($this->table) . $this->compileWheres(), $this->bindings);
        return true;
    }

    protected function assertHasWhere(bool $all, string $operation): void
    {
        if (!$all && !$this->wheres) {
            throw new \LogicException("Refusing to $operation every row of {$this->table} without a where(). Pass true to allow it.");
        }
    }

    public function truncate(): bool
    {
        $table = $this->wrapTable($this->table);
        if ($this->isSqlite()) {
            $this->run("DELETE FROM $table", []);
            // Restart the ids at 1 (sqlite_sequence only exists once an AUTOINCREMENT table does)
            try {
                $this->run('DELETE FROM sqlite_sequence WHERE name = ?', [$this->table]);
            } catch (\PDOException) {
            }
        } elseif ($this->isPgsql()) {
            $this->run("TRUNCATE TABLE $table RESTART IDENTITY CASCADE", []);
        } else {
            $this->run("TRUNCATE TABLE $table", []);
        }
        return true;
    }
}
