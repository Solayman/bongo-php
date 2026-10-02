<?php
namespace System\Database;

/**
 * Parent has many related rows: related.foreignKey = parent.localKey
 */
class HasMany extends Relation
{
    protected function constrain(): void
    {
        $this->query->where($this->foreignKey, $this->parent->getAttribute($this->otherKey));
    }

    public function getResults(): array
    {
        return $this->query->get();
    }

    /** Create a related row with the foreign key filled in */
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
        $grouped = [];
        if ($keys) {
            foreach (($this->related)::query()->whereIn($this->foreignKey, $keys)->get() as $child) {
                $grouped[$child->getAttribute($this->foreignKey)][] = $child;
            }
        }
        foreach ($models as $model) {
            $model->setRelation($name, $grouped[$model->getAttribute($this->otherKey)] ?? []);
        }
    }
}
