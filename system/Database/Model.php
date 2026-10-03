<?php

namespace System\Database;

use ReflectionClass;

/**
 * A table row as an object.
 *
 *   class Product extends Model
 *   {
 *       protected static $table = 'products';
 *       protected array $fillable = ['name', 'sku', 'price', 'stock'];
 *       protected array $hidden = [];
 *       protected array $casts = ['price' => 'float', 'stock' => 'int'];
 *
 *       public function category() { return $this->belongsTo(Category::class); }
 *   }
 *
 *   $p = Product::create(['name' => 'Mug', 'sku' => 'M-1', 'price' => 5]);
 *   $p->price = 6; $p->save();               // UPDATE only the changed column
 *   $p->category->name;                       // relation as a property (loaded once)
 *   Order::with('items')->latest()->get();    // eager loading: 2 queries for any number of orders
 */
class Model implements \JsonSerializable
{
    protected static $table = null;
    protected static string $primaryKey = 'id';

    /** Columns create()/fill() may set. Empty = every column not in $guarded */
    protected array $fillable = [];
    /** Columns create()/fill() must never set */
    protected array $guarded = ['id'];
    /** Columns left out of toArray()/JSON (e.g. password) */
    protected array $hidden = [];
    /** column => int|float|bool|string|array|json */
    protected array $casts = [];

    protected array $attributes = [];
    protected array $original = [];
    protected array $relations = [];
    public bool $exists = false;

    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    // ------------------------------------------------------------------
    // Static API
    // ------------------------------------------------------------------

    /** Table name: $table, or the class name in lowercase + "s" (Product → products) */
    public static function tableName(): string
    {
        if (static::$table) {
            return static::$table;
        }
        return strtolower((new ReflectionClass(static::class))->getShortName()) . 's';
    }

    public static function primaryKey(): string
    {
        return static::$primaryKey;
    }

    /** A query builder that returns instances of this model */
    public static function query(): QueryBuilder
    {
        return (new QueryBuilder(static::tableName()))->setModel(static::class);
    }

    public static function where($column, $operator = null, $value = null): QueryBuilder
    {
        return static::query()->where(...func_get_args());
    }

    public static function whereIn(string $column, array $values): QueryBuilder
    {
        return static::query()->whereIn($column, $values);
    }

    public static function with(string ...$relations): QueryBuilder
    {
        return static::query()->with(...$relations);
    }

    public static function latest(string $column = 'created_at'): QueryBuilder
    {
        return static::query()->latest($column);
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    public static function find($id): ?static
    {
        return $id === null ? null : static::query()->where(static::$primaryKey, $id)->first();
    }

    /** find() or a 404 page */
    public static function findOrFail($id): static
    {
        $model = static::find($id);
        if ($model === null) {
            throw new \System\Http\HttpException(404, (new ReflectionClass(static::class))->getShortName() . ' not found');
        }
        return $model;
    }

    /** Insert a row (only fillable columns) and return the saved model */
    public static function create(array $attributes): static
    {
        $model = new static($attributes);
        $model->save();
        return $model;
    }

    /** Delete by primary key(s); returns how many existed */
    public static function destroy(int|string|array $ids): int
    {
        $ids = (array) $ids;
        $count = static::query()->whereIn(static::$primaryKey, $ids)->count();
        if ($count) {
            static::query()->whereIn(static::$primaryKey, $ids)->delete();
        }
        return $count;
    }

    /** Build a model from a database row (used by the query builder) */
    public static function hydrate(array $row): static
    {
        $model = new static();
        $model->attributes = $row;
        $model->original = $row;
        $model->exists = true;
        return $model;
    }

    // ------------------------------------------------------------------
    // Attributes
    // ------------------------------------------------------------------

    /** Mass-assign respecting $fillable / $guarded */
    public function fill(array $attributes): static
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->setAttribute($key, $value);
            }
        }
        return $this;
    }

    public function isFillable(string $key): bool
    {
        if (in_array($key, $this->guarded, true) && !in_array($key, $this->fillable, true)) {
            return false;
        }
        return $this->fillable === [] || in_array($key, $this->fillable, true);
    }

    public function getAttribute(string $key)
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->castGet($key, $this->attributes[$key]);
        }
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }
        // A relation method used as a property is loaded once and remembered
        if (method_exists($this, $key) && !method_exists(self::class, $key)) {
            $relation = $this->$key();
            if ($relation instanceof Relation) {
                return $this->relations[$key] = $relation->getResults();
            }
        }
        return null;
    }

    public function setAttribute(string $key, $value): void
    {
        $this->attributes[$key] = $this->castSet($key, $value);
    }

    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    public function __set($key, $value)
    {
        $this->setAttribute($key, $value);
    }

    public function __isset($key)
    {
        return $this->getAttribute($key) !== null;
    }

    public function __unset($key)
    {
        unset($this->attributes[$key], $this->relations[$key]);
    }

    public function getKey()
    {
        return $this->attributes[static::$primaryKey] ?? null;
    }

    /** Changed columns since the model was loaded or saved */
    public function getDirty(): array
    {
        $dirty = [];
        foreach ($this->attributes as $key => $value) {
            if (!array_key_exists($key, $this->original) || (string) $this->original[$key] !== (string) $value) {
                $dirty[$key] = $value;
            }
        }
        return $dirty;
    }

    public function isDirty(?string $key = null): bool
    {
        $dirty = $this->getDirty();
        return $key === null ? $dirty !== [] : array_key_exists($key, $dirty);
    }

    public function getOriginal(?string $key = null)
    {
        return $key === null ? $this->original : ($this->original[$key] ?? null);
    }

    // ------------------------------------------------------------------
    // Persistence
    // ------------------------------------------------------------------

    /** INSERT a new model, or UPDATE only the changed columns of an existing one */
    public function save(): bool
    {
        if ($this->exists) {
            $dirty = $this->getDirty();
            unset($dirty[static::$primaryKey]);
            if ($dirty) {
                (new QueryBuilder(static::tableName()))->where(static::$primaryKey, $this->getKey())->update($dirty);
            }
        } else {
            $id = (new QueryBuilder(static::tableName()))->insertGetId($this->attributes);
            if ($id && !isset($this->attributes[static::$primaryKey])) {
                $this->attributes[static::$primaryKey] = $id;
            }
            $this->exists = true;
            // Pick up database defaults (created_at, status, ...)
            $this->refresh();
        }

        $this->original = $this->attributes;
        return true;
    }

    /** fill() + save() */
    public function update(array $attributes): bool
    {
        return $this->fill($attributes)->save();
    }

    public function delete(): bool
    {
        if (!$this->exists) {
            return false;
        }
        (new QueryBuilder(static::tableName()))->where(static::$primaryKey, $this->getKey())->delete();
        $this->exists = false;
        return true;
    }

    /** Reload columns from the database (relations are cleared) */
    public function refresh(): static
    {
        $row = (new QueryBuilder(static::tableName()))->asArray()->where(static::$primaryKey, $this->getKey())->first();
        if ($row) {
            $this->attributes = $row;
            $this->original = $row;
        }
        $this->relations = [];
        return $this;
    }

    // ------------------------------------------------------------------
    // Relations
    // ------------------------------------------------------------------

    /** One-to-many: an Order hasMany OrderItem (order_items.order_id) */
    protected function hasMany(string $related, ?string $foreignKey = null, ?string $localKey = null): HasMany
    {
        $foreignKey ??= $this->defaultForeignKey();
        return new HasMany($this, $related, $foreignKey, $localKey ?? static::$primaryKey);
    }

    /** Inverse: an OrderItem belongsTo an Order (order_items.order_id → orders.id) */
    protected function belongsTo(string $related, ?string $foreignKey = null, ?string $ownerKey = null): BelongsTo
    {
        $foreignKey ??= strtolower((new ReflectionClass($related))->getShortName()) . '_id';
        return new BelongsTo($this, $related, $foreignKey, $ownerKey ?? $related::primaryKey());
    }

    protected function defaultForeignKey(): string
    {
        return strtolower((new ReflectionClass(static::class))->getShortName()) . '_id';
    }

    public function setRelation(string $name, $value): void
    {
        $this->relations[$name] = $value;
    }

    public function relationLoaded(string $name): bool
    {
        return array_key_exists($name, $this->relations);
    }

    /**
     * Load relations for many models with one query per relation (used by with())
     *
     * @param static[] $models
     */
    public static function eagerLoad(array $models, array $relations): void
    {
        foreach ($relations as $name) {
            $relation = $models[0]->$name();
            if (!$relation instanceof Relation) {
                throw new \LogicException(static::class . "::$name() is not a relation");
            }
            $relation->eagerLoad($models, $name);
        }
    }

    // ------------------------------------------------------------------
    // Output
    // ------------------------------------------------------------------

    public function toArray(): array
    {
        $data = [];
        foreach ($this->attributes as $key => $value) {
            if (!in_array($key, $this->hidden, true)) {
                $data[$key] = $this->castGet($key, $value);
            }
        }
        foreach ($this->relations as $key => $value) {
            $data[$key] = is_array($value)
                ? array_map(fn($m) => $m instanceof self ? $m->toArray() : $m, $value)
                : ($value instanceof self ? $value->toArray() : $value);
        }
        return $data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * What dd($model) / dump($model) shows: the columns first (hidden ones as "***"),
     * then loaded relations, then unsaved changes. The model's settings ($fillable,
     * $casts ...) are left out: they are in the class, not in the data.
     */
    public function __debugInfo(): array
    {
        $info = [];
        foreach ($this->attributes as $key => $value) {
            $info[$key] = in_array($key, $this->hidden, true) ? '***' : $this->castGet($key, $value);
        }
        foreach ($this->relations as $key => $value) {
            $info[$key] = $value;
        }
        if (!$this->exists) {
            $info['(not saved yet)'] = true;
        } elseif ($dirty = $this->getDirty()) {
            $changes = [];
            foreach ($dirty as $key => $value) {
                $old = $this->original[$key] ?? null;
                $changes[$key] = in_array($key, $this->hidden, true) ? 'changed' : var_export($old, true) . ' → ' . var_export($value, true);
            }
            $info['(unsaved changes)'] = $changes;
        }
        return $info;
    }

    public function toJson(int $flags = 0): string
    {
        return json_encode($this->toArray(), $flags);
    }

    // ------------------------------------------------------------------
    // Casts
    // ------------------------------------------------------------------

    protected function castGet(string $key, $value)
    {
        if ($value === null || !isset($this->casts[$key])) {
            return $value;
        }
        return match ($this->casts[$key]) {
            'int', 'integer' => (int) $value,
            'float', 'double', 'decimal' => (float) $value,
            'bool', 'boolean' => (bool) $value,
            'string' => (string) $value,
            'array', 'json' => is_string($value) ? (json_decode($value, true) ?? []) : $value,
            default => $value,
        };
    }

    protected function castSet(string $key, $value)
    {
        if (in_array($this->casts[$key] ?? null, ['array', 'json'], true) && !is_string($value) && $value !== null) {
            return json_encode($value);
        }
        if (in_array($this->casts[$key] ?? null, ['bool', 'boolean'], true) && is_bool($value)) {
            return (int) $value;
        }
        return $value;
    }
}
