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
}
