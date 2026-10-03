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
        $this->constrain();
    }

    /** Limit the query to rows related to $parent */
    abstract protected function constrain(): void;

    /** The related model(s) for $parent */
    abstract public function getResults();

    /** Load the relation for many parents with one query and attach it to each */
    abstract public function eagerLoad(array $models, string $name): void;

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
        if (!$this instanceof HasMany && !$this instanceof HasOne) {
            throw new \LogicException('withCount() works for hasMany, hasOne and belongsToMany relations');
        }
        $related = ($this->related)::tableName();
        return "(SELECT COUNT(*) FROM $related WHERE $related.{$this->foreignKey} = $parentTable.{$this->otherKey}"
            . ($this->related::usesSoftDeletes() ? " AND $related.deleted_at IS NULL" : '') . ')';
    }
}
