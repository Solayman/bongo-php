<?php

namespace System\Database;

/**
 * Base for hasMany / belongsTo. Unknown method calls go to the underlying
 * query, so $order->items()->where('quantity', '>', 1)->get() works.
 */
abstract class Relation
{
    protected QueryBuilder $query;

    public function __construct(
        protected Model $parent,
        protected string $related,
        protected string $foreignKey,
        protected string $otherKey
    ) {
        $this->query = $related::query();
        if ($parent->getConnection() !== null && $related::getConnectionName() === null) {
            $this->query->setConnection($parent->getConnection());
        }
        $this->constrain();
    }

    public function getQuery(): QueryBuilder
    {
        return $this->query;
    }

    /** Create a new query for the related model inheriting active connection */
    protected function newRelatedQuery(): QueryBuilder
    {
        $query = ($this->related)::query();
        if ($this->query->getConnection() !== null && ($this->related)::getConnectionName() === null) {
            $query->setConnection($this->query->getConnection());
        }
        return $query;
    }

    /** Limit the query to rows related to $parent */
    abstract protected function constrain(): void;

    /** The related model(s) for $parent */
    abstract public function getResults();

    /** Load the relation for many parents with one query and attach it to each */
    abstract public function eagerLoad(array $models, string $name): void;

    /** The distinct non-null values of $attribute across $models (for one whereIn) */
    protected static function keysOf(array $models, string $attribute): array
    {
        return array_values(array_unique(array_filter(array_map(fn($m) => $m->getAttribute($attribute), $models), fn($k) => $k !== null)));
    }

    public function __call(string $method, array $args)
    {
        $result = $this->query->$method(...$args);
        return $result === $this->query ? $this : $result;
    }

    public function get(): array
    {
        return $this->query->get();
    }

    public function first(): ?Model
    {
        return $this->query->first();
    }

    /**
     * For withCount(): "(SELECT COUNT(*) FROM related WHERE ...)" counting this relation's rows
     * for each row of $parentTable (soft-deleted related rows are not counted).
     */
    public function countSubquery(string $parentTable): string
    {
        if (!$this instanceof HasOneOrMany) {
            throw new \LogicException('withCount() works for hasMany, hasOne and belongsToMany relations');
        }
        $related = ($this->related)::tableName();
        return "(SELECT COUNT(*) FROM $related WHERE $related.{$this->foreignKey} = $parentTable.{$this->otherKey}"
            . ($this->related::usesSoftDeletes() ? " AND $related.deleted_at IS NULL" : '') . ')';
    }
}
