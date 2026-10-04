<?php

namespace System\Database;

/**
 * Shared by hasMany and hasOne: related.foreignKey = parent.localKey
 */
abstract class HasOneOrMany extends Relation
{
    /** hasOne: each parent gets the first matching row (or null) instead of a list */
    protected bool $one = false;

    protected function constrain(): void
    {
        $this->query->where($this->foreignKey, $this->parent->getAttribute($this->otherKey));
    }

    /** Create a related row with the foreign key filled in */
    public function create(array $attributes): Model
    {
        $model = new $this->related($attributes);
        if ($this->query->getConnection() !== null) {
            $model->setConnection($this->query->getConnection());
        }
        $model->setAttribute($this->foreignKey, $this->parent->getAttribute($this->otherKey));
        $model->save();
        return $model;
    }

    public function eagerLoad(array $models, string $name): void
    {
        $byParent = [];
        if ($keys = self::keysOf($models, $this->otherKey)) {
            $query = $this->newRelatedQuery();
            foreach ($query->whereIn($this->foreignKey, $keys)->get() as $child) {
                $key = $child->getAttribute($this->foreignKey);
                if ($this->one) {
                    $byParent[$key] ??= $child;
                } else {
                    $byParent[$key][] = $child;
                }
            }
        }
        foreach ($models as $model) {
            $model->setRelation($name, $byParent[$model->getAttribute($this->otherKey)] ?? ($this->one ? null : []));
        }
    }
}
