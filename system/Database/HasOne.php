<?php
namespace System\Database;

/**
 * Parent has one related row: related.foreignKey = parent.localKey ($user->profile)
 */
class HasOne extends Relation
{
    protected function constrain(): void
    {
        $this->query->where($this->foreignKey, $this->parent->getAttribute($this->otherKey));
    }

    public function getResults(): ?Model
    {
        return $this->query->first();
    }

    /** Create the related row with the foreign key filled in */
    public function create(array $attributes): Model
    {
        $model = new $this->related($attributes);
        $model->setAttribute($this->foreignKey, $this->parent->getAttribute($this->otherKey));
        $model->save();
        return $model;
    }

    public function eagerLoad(array $models, string $name): void
    {
        $keys = array_values(array_unique(array_filter(array_map(fn($m) => $m->getAttribute($this->otherKey), $models), fn($k) => $k !== null)));
        $byParent = [];
        if ($keys) {
            foreach (($this->related)::query()->whereIn($this->foreignKey, $keys)->get() as $child) {
                $byParent[$child->getAttribute($this->foreignKey)] ??= $child;
            }
        }
        foreach ($models as $model) {
            $model->setRelation($name, $byParent[$model->getAttribute($this->otherKey)] ?? null);
        }
    }
}
