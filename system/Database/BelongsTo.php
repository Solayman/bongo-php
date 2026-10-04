<?php

namespace System\Database;

/**
 * Child belongs to one parent: child.foreignKey = parent.ownerKey
 */
class BelongsTo extends Relation
{
    protected function constrain(): void
    {
        $this->query->where($this->otherKey, $this->parent->getAttribute($this->foreignKey));
    }

    public function getResults(): ?Model
    {
        return $this->parent->getAttribute($this->foreignKey) === null ? null : $this->query->first();
    }

    public function eagerLoad(array $models, string $name): void
    {
        $keys = self::keysOf($models, $this->foreignKey);
        $owners = [];
        if ($keys) {
            $query = $this->newRelatedQuery();
            foreach ($query->whereIn($this->otherKey, $keys)->get() as $owner) {
                $owners[$owner->getAttribute($this->otherKey)] = $owner;
            }
        }
        foreach ($models as $model) {
            $model->setRelation($name, $owners[$model->getAttribute($this->foreignKey)] ?? null);
        }
    }
}
