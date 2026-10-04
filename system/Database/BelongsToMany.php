<?php
namespace System\Database;

/**
 * Many-to-many through a pivot table: products ⇄ product_tag ⇄ tags.
 *   $product->tags;                      // Tag models
 *   $product->tags()->attach([1, 2]);    // add rows to the pivot table
 *   $product->tags()->detach(2);         $product->tags()->detach();   // all
 *   $product->tags()->sync([1, 3]);      // exactly these
 */
class BelongsToMany extends Relation
{
    public function __construct(
        Model $parent,
        string $related,
        protected string $pivot,
        protected string $parentKey,     // pivot column pointing at the parent (product_id)
        protected string $relatedKey     // pivot column pointing at the related model (tag_id)
    ) {
        parent::__construct($parent, $related, $parentKey, $relatedKey);
    }

    protected function constrain(): void
    {
        $table = ($this->related)::tableName();
        $this->query->select("$table.*")
            ->join($this->pivot, "{$this->pivot}.{$this->relatedKey}", '=', "$table." . ($this->related)::primaryKey())
            ->where("{$this->pivot}.{$this->parentKey}", $this->parent->getKey());
    }

    /** For withCount('tags'): the number of links in the pivot table */
    public function countSubquery(string $parentTable): string
    {
        return "(SELECT COUNT(*) FROM {$this->pivot} WHERE {$this->pivot}.{$this->parentKey} = $parentTable."
            . $this->parent::primaryKey() . ')';
    }

    public function getResults(): array
    {
        return $this->query->get();
    }

    /** Add links (already linked ids are skipped) */
    public function attach(int|array $ids): void
    {
        $conn = $this->query->getConnection();
        $existing = $this->linkedIds();
        foreach ((array) $ids as $id) {
            if (!in_array((int) $id, $existing, true)) {
                DB::table($this->pivot, $conn)->insert([$this->parentKey => $this->parent->getKey(), $this->relatedKey => (int) $id]);
            }
        }
    }

    /** Remove some links, or all of them */
    public function detach(int|array|null $ids = null): void
    {
        $conn = $this->query->getConnection();
        $query = DB::table($this->pivot, $conn)->where($this->parentKey, $this->parent->getKey());
        if ($ids !== null) {
            $query->whereIn($this->relatedKey, array_map('intval', (array) $ids));
        }
        $query->delete(true);
    }

    /** Keep exactly these links: ['attached' => [...], 'detached' => [...]] */
    public function sync(array $ids): array
    {
        $ids = array_map('intval', $ids);
        $existing = $this->linkedIds();
        $detach = array_values(array_diff($existing, $ids));
        $attach = array_values(array_diff($ids, $existing));
        if ($detach) $this->detach($detach);
        if ($attach) $this->attach($attach);
        return ['attached' => $attach, 'detached' => $detach];
    }

    protected function linkedIds(): array
    {
        $conn = $this->query->getConnection();
        return array_map('intval', DB::table($this->pivot, $conn)->where($this->parentKey, $this->parent->getKey())->pluck($this->relatedKey));
    }

    public function eagerLoad(array $models, string $name): void
    {
        $ids = self::keysOf($models, $this->parent::primaryKey());
        $grouped = [];
        if ($ids) {
            $table = ($this->related)::tableName();
            $query = $this->newRelatedQuery();
            $rows = $query
                ->select("$table.*", "{$this->pivot}.{$this->parentKey} AS __pivot_parent")
                ->join($this->pivot, "{$this->pivot}.{$this->relatedKey}", '=', "$table." . ($this->related)::primaryKey())
                ->whereIn("{$this->pivot}.{$this->parentKey}", $ids)
                ->get();
            foreach ($rows as $row) {
                $parentId = $row->getAttribute('__pivot_parent');
                unset($row->__pivot_parent);
                $grouped[$parentId][] = $row;
            }
        }
        foreach ($models as $model) {
            $model->setRelation($name, $grouped[$model->getKey()] ?? []);
        }
    }
}
