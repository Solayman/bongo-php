<?php

namespace System\Database;

use ReflectionClass;

class Model
{
    protected static $table = null;
    protected array $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->attributes = $attributes;
    }

    /**
     * Get the table name (from property or class name)
     */
    public static function tableName(): string
    {
        if (static::$table) {
            return static::$table;
        }

        $class = strtolower((new ReflectionClass(static::class))->getShortName());
        return $class . 's'; // e.g. User -> users
    }

    /**
     * Get a QueryBuilder for this model
     */
    public static function query(): QueryBuilder
    {
        return new QueryBuilder(static::tableName());
    }

    // === Static query methods ===
    public static function where(string $column, $operator = null, $value = null): QueryBuilder
    {
        // Forward the real argument count so where('col', 'value') keeps working
        return static::query()->where(...func_get_args());
    }

    public static function whereIn(string $column, array $values): QueryBuilder
    {
        return static::query()->whereIn($column, $values);
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function find($id): ?object
    {
        return static::query()->where('id', '=', $id)->first();
    }

    public static function create(array $data): bool
    {
        return static::query()->insert($data);
    }

    // === Instance attribute access ===
    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }
}
